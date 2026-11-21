<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Technical + fundamental check of DSE shares for the Stock Analysis page.
 * Prices: dse.com.bd day-end archive API. Fundamentals: dse.com.bd company page.
 * analyze() is pure (bars in, signals out) so it can be unit-tested.
 */
class StockAnalysis
{
    private const BASE = 'https://www.dse.com.bd';
    private const UA = 'Mozilla/5.0 (PCM personal portfolio; analysis)';
    public const CIRCUIT = 0.10; // DSE daily price limit ±10%

    /** Oldest → newest daily bars for one symbol (about 120 calendar days ≈ 80 trading days). */
    public static function history(string $symbol, int $days = 120): array
    {
        return self::warm([$symbol], true, $days)[$symbol]['bars'];
    }

    /**
     * History and company data for many symbols at once: cached ones instantly, the rest fetched in parallel.
     * A failed fetch is not cached, so the next page load tries again.
     * @return array<string, array{bars: array, fund: array}>
     */
    public static function warm(array $symbols, bool $withHistory = true, int $days = 120): array
    {
        $histKey = fn($s) => "dse_hist_{$s}_" . now()->toDateString();
        $fundKey = fn($s) => "dse_fund_{$s}";
        $out = [];
        foreach ($symbols as $s) {
            $fund = Cache::get($fundKey($s));
            // No category = an old save of a DSE error page: fetch it again.
            $out[$s] = ['bars' => $withHistory ? (Cache::get($histKey($s)) ?: []) : [], 'fund' => !empty($fund['category']) ? $fund : ['symbol' => $s]];
        }
        $missing = array_filter($symbols, fn($s) => ($withHistory && !$out[$s]['bars']) || empty($out[$s]['fund']['category']));

        foreach (array_chunk(array_values($missing), 4) as $batch) { // a few at a time, to stay polite to dse.com.bd
            $replies = Http::pool(function ($pool) use ($batch, $out, $withHistory, $days) {
                $calls = [];
                foreach ($batch as $s) {
                    if ($withHistory && !$out[$s]['bars']) {
                        $calls[] = $pool->as("h:$s")->timeout(12)->withHeaders(['User-Agent' => self::UA])->get(self::BASE . '/api/live/data-archive/day-end',
                            ['from' => now()->subDays($days)->toDateString(), 'to' => now()->toDateString(), 'inst' => $s, 'page' => 1]);
                    }
                    if (empty($out[$s]['fund']['category'])) {
                        $calls[] = $pool->as("f:$s")->timeout(12)->withHeaders(['User-Agent' => self::UA])->get(self::BASE . '/company/' . rawurlencode($s));
                    }
                }
                return $calls;
            });
            $answered = 0;
            foreach ($replies as $key => $r) {
                if (!$r instanceof \Illuminate\Http\Client\Response || !$r->successful()) continue; // timeouts come back as exceptions
                $answered++;
                [$kind, $s] = explode(':', $key, 2);
                if ($kind === 'h' && ($bars = self::bars($r->json('rows') ?? []))) {
                    Cache::put($histKey($s), $bars, now()->addHours(6));
                    $out[$s]['bars'] = $bars;
                } elseif ($kind === 'f') {
                    $out[$s]['fund'] = self::parseCompany($r->body(), $s);
                    // A maintenance page also returns 200 but has no category: never keep it.
                    if ($out[$s]['fund']['category']) Cache::put($fundKey($s), $out[$s]['fund'], now()->addHours(12));
                }
            }
            if (!$answered) break; // DSE is down: don't wait 12 seconds for every remaining group
        }
        return $out;
    }

    /**
     * Buy / Sell / Hold for each of the last $count candles, using only the prices known on that day.
     * Fundamentals block a Buy on weak companies (Z category, negative NAV) and when company data is missing.
     * @return array<string, array{call: string, meaning: string}> keyed by bar date
     */
    public static function candleCalls(array $bars, array $fund = [], int $count = 15): array
    {
        $unknown = empty($fund['category']);
        $weak = ($fund['category'] ?? null) === 'Z' || ($fund['nav'] ?? 1) < 0;
        $out = [];
        for ($i = max(0, count($bars) - $count); $i < count($bars); $i++) {
            $a = self::analyze(array_slice($bars, 0, $i + 1));
            if (!$a['ok']) continue;
            $up = array_keys(array_filter(['price above MA14' => $a['signals']['MA14 buy'], 'MACD rising' => $a['signals']['MACD buy'],
                'bullish candle' => $a['signals']['Hammer'], 'three rising days' => $a['signals']['Escalating']]));
            $why = $up ? implode(', ', $up) : 'no buy signal';
            $out[$bars[$i]['date']] = match (true) {
                $a['rsi'] !== null && $a['rsi'] > 72 => ['call' => 'Sell', 'meaning' => 'Overbought (RSI ' . round($a['rsi']) . '): a pullback is likely, so take profit if you hold.'],
                $a['bearish'] && $a['tech_score'] < 3 => ['call' => 'Sell', 'meaning' => 'Bearish candle with a weak trend: sellers are in control, so reduce or exit.'],
                $a['close'] < $a['ma14'] && $a['hist'] < 0 && $a['tech_score'] <= 0 => ['call' => 'Sell', 'meaning' => 'Below MA14 with falling momentum: the downtrend continues, so do not add.'],
                $a['tech_score'] >= 4 && !$a['bearish'] && $unknown => ['call' => 'Hold', 'meaning' => "Chart looks good ($why), but company data is missing: do not buy until it loads."],
                $a['tech_score'] >= 4 && !$a['bearish'] && $weak => ['call' => 'Hold', 'meaning' => "Chart looks good ($why), but the company is weak: do not buy."],
                $a['tech_score'] >= 4 && !$a['bearish'] => ['call' => 'Buy', 'meaning' => "Momentum is strong ($why): a possible entry inside the buy range."],
                default => ['call' => 'Hold', 'meaning' => "No clear edge ($why): keep what you hold and wait."],
            };
        }
        return $out;
    }

    /** Every instrument's bars for the last $days, grouped by symbol (one paged fetch, cached). */
    public static function allHistory(int $days = 60): array
    {
        $today = now()->toDateString();
        if ($cached = Cache::get("dse_hist_all_{$today}")) return $cached;

        $by = [];
        $from = now()->subDays($days)->toDateString();
        for ($page = 1, $pages = 1; $page <= $pages && $page <= 80; $page++) {
            $r = Http::timeout(20)->retry(2, 1000)->withHeaders(['User-Agent' => self::UA])
                ->get(self::BASE . '/api/live/data-archive/day-end', ['from' => $from, 'to' => $today, 'page' => $page])
                ->throw(); // a missing page would silently give wrong indicators
            foreach ($r->json('rows') ?? [] as $row) $by[$row['tradingCode']][] = $row;
            $pages = (int) ceil(($r->json('total') ?? 0) / max(1, $r->json('pageSize') ?? 500));
        }
        $all = array_map(fn($rows) => self::bars($rows), $by);
        if ($all) {
            Cache::put("dse_hist_all_{$today}", $all, now()->addHours(6));
            // Each share on its own too, so a popup need not load the whole market.
            Cache::putMany(collect($all)->mapWithKeys(fn($bars, $s) => ["dse_scan_{$s}_{$today}" => $bars])->all(), now()->addHours(6));
        }
        return $all;
    }

    /** One share's bars from today's saved scan; never downloads. Empty when there is no saved scan. */
    public static function scanBars(string $symbol): array
    {
        $today = now()->toDateString();
        return Cache::get("dse_scan_{$symbol}_{$today}") ?: (Cache::get("dse_hist_all_{$today}")[$symbol] ?? []);
    }

    /** API rows → [date, open, high, low, close, volume] oldest first; days with no trade are dropped. */
    public static function bars(array $rows): array
    {
        $bars = [];
        foreach ($rows as $r) {
            if (($r['volume'] ?? 0) <= 0 || ($r['high'] ?? 0) <= 0) continue;
            $close = (float) ($r['closep'] ?: $r['ltp']);
            $bars[] = ['date' => $r['date'], 'open' => (float) ($r['openp'] ?: $r['ycp']), 'high' => (float) $r['high'],
                'low' => (float) $r['low'], 'close' => $close, 'volume' => (int) $r['volume']];
        }
        usort($bars, fn($a, $b) => strcmp($a['date'], $b['date']));
        return $bars;
    }

    /** Key figures from the company page; null fields when DSE doesn't show them. */
    public static function fundamentals(string $symbol): array
    {
        return self::warm([$symbol], false)[$symbol]['fund'];
    }

    public static function parseCompany(string $html, string $symbol): array
    {
        // Tags become spaces (not strip_tags): labels and values sit in adjacent <div>s with no space between.
        $t = html_entity_decode(preg_replace('/<[^>]+>/', ' ', preg_replace('#<(script|style)\b[^>]*>.*?</\1>#si', ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace('/[\s\x{00A0}\x{2009}\x{202F}]+/u', ' ', $t); // DSE puts non-breaking spaces between label, "BDT" and value
        $num = fn($re) => preg_match($re, $t, $m) ? (float) str_replace(',', '', $m[1]) : null;
        $q = preg_quote($symbol, '/');
        $category = preg_match("/Company \/ {$q} Back to market {$q} ([ABGNZ])\b/", $t, $m) ? $m[1] : null;
        $w52 = preg_match('/52-week range Current BDT [\d,.]+ BDT ([\d,.]+) BDT ([\d,.]+)/', $t, $m) ? [(float) str_replace(',', '', $m[1]), (float) str_replace(',', '', $m[2])] : [null, null];
        $sponsor = preg_match('/Sponsor \/ Director ((?:[\d.]+ % ?)+)/', $t, $m) && preg_match_all('/[\d.]+/', $m[1], $all) ? (float) end($all[0]) : null;

        return [
            'symbol' => $symbol, 'category' => $category,
            'sector' => preg_match('/Debut \S+ \S+ (.+?) DSE PUBLIC/u', $t, $m) ? trim($m[1]) : null,
            'nav' => $num('/NAV per share BDT (-?[\d,.]+)/'),
            'pe' => $num('/P\/E \(audited\) ([\d,.\-]+)x/'),
            'low52' => $w52[0], 'high52' => $w52[1], 'sponsor' => $sponsor,
            'year_end' => preg_match('/Year-end (\w+)/', $t, $m) ? $m[1] : null,
            'short_loan' => $num('/Short-term: BDT ([\d,.]+) mn/'),
        ];
    }

    private static function sma(array $v, int $n): ?float
    {
        return count($v) >= $n ? array_sum(array_slice($v, -$n)) / $n : null;
    }

    private static function ema(array $v, int $n): array
    {
        $k = 2 / ($n + 1);
        $out = [];
        $prev = null;
        foreach ($v as $i => $x) {
            $prev = $prev === null ? $x : $x * $k + $prev * (1 - $k);
            $out[$i] = $prev;
        }
        return $out;
    }

    private static function rsi(array $c, int $n = 14): ?float
    {
        if (count($c) <= $n) return null;
        $gain = $loss = 0.0;
        for ($i = 1; $i <= $n; $i++) { $d = $c[$i] - $c[$i - 1]; $d > 0 ? $gain += $d : $loss -= $d; }
        $gain /= $n; $loss /= $n;
        for ($i = $n + 1; $i < count($c); $i++) { // Wilder smoothing
            $d = $c[$i] - $c[$i - 1];
            $gain = ($gain * ($n - 1) + max($d, 0)) / $n;
            $loss = ($loss * ($n - 1) + max(-$d, 0)) / $n;
        }
        return $loss == 0 ? 100.0 : 100 - 100 / (1 + $gain / $loss);
    }

    /** Candlestick pattern names for bar $i (needs bar $i-1 for two-bar patterns). */
    public static function patterns(array $bars, int $i): array
    {
        $b = $bars[$i];
        $range = $b['high'] - $b['low'];
        if ($range <= 0) return [];
        $body = abs($b['close'] - $b['open']);
        $upper = $b['high'] - max($b['open'], $b['close']);
        $lower = min($b['open'], $b['close']) - $b['low'];
        $p = [];
        $prevDown = $i >= 3 && $bars[$i - 1]['close'] < $bars[$i - 3]['close'];
        $prevUp = $i >= 3 && $bars[$i - 1]['close'] > $bars[$i - 3]['close'];
        // Long-shadow candles first: a tiny body with a long lower wick is still a hammer (dragonfly), not a plain doji.
        $small = max($body, 0.1 * $range);
        if ($lower >= 0.6 * $range && $upper <= $small) $p[] = $prevDown ? 'Hammer (bullish)' : 'Hanging man';
        elseif ($upper >= 0.6 * $range && $lower <= $small) $p[] = $prevUp ? 'Shooting star (bearish)' : 'Inverted hammer';
        elseif ($body <= 0.1 * $range) $p[] = 'Doji';
        elseif ($body <= 0.35 * $range && $upper >= $body * 0.6 && $lower >= $body * 0.6) $p[] = 'Spinning top';
        elseif ($body >= 0.9 * $range) $p[] = $b['close'] > $b['open'] ? 'Bullish marubozu' : 'Bearish marubozu';
        if ($i > 0) {
            $a = $bars[$i - 1];
            if ($a['close'] < $a['open'] && $b['close'] > $b['open'] && $b['close'] >= $a['open'] && $b['open'] <= $a['close']) $p[] = 'Bullish engulfing';
            if ($a['close'] > $a['open'] && $b['close'] < $b['open'] && $b['open'] >= $a['close'] && $b['close'] <= $a['open']) $p[] = 'Bearish engulfing';
        }
        return $p;
    }

    /**
     * Pure: signals, levels and a trade plan from daily bars (oldest first).
     * $holding: ['qty','avg_cost'] when the share is held, for the zero-loss sell price.
     */
    public static function analyze(array $bars, array $fund = [], ?array $holding = null): array
    {
        $n = count($bars);
        if ($n < 20) return ['ok' => false, 'reason' => 'Fewer than 20 trading days of data'];
        $c = array_column($bars, 'close');
        $v = array_column($bars, 'volume');
        $last = $bars[$n - 1];
        $close = $last['close'];

        $ma14 = self::sma($c, 14);
        $ma14Prev = self::sma(array_slice($c, 0, -1), 14);
        $e12 = self::ema($c, 12);
        $e26 = self::ema($c, 26);
        $macd = array_map(fn($a, $b) => $a - $b, $e12, $e26);
        $sig = self::ema($macd, 9);
        $hist = $macd[$n - 1] - $sig[$n - 1];
        $histPrev = $macd[$n - 2] - $sig[$n - 2];
        $rsi = self::rsi($c);
        $tr = [];
        for ($i = max(1, $n - 14); $i < $n; $i++) $tr[] = max($bars[$i]['high'] - $bars[$i]['low'], abs($bars[$i]['high'] - $c[$i - 1]), abs($bars[$i]['low'] - $c[$i - 1]));
        $atr = array_sum($tr) / count($tr);
        $win = array_slice($bars, -20);
        $support = min(array_column($win, 'low'));
        $resistance = max(array_column($win, 'high'));
        $volAvg = self::sma($v, 20);
        $obvUp = 0;
        for ($i = $n - 10; $i < $n; $i++) $obvUp += $c[$i] > $c[$i - 1] ? $v[$i] : ($c[$i] < $c[$i - 1] ? -$v[$i] : 0);

        $candles = [];
        for ($i = max(1, $n - 15); $i < $n; $i++) {
            $b = $bars[$i];
            $candles[] = $b + ['change' => $c[$i - 1] ? $b['close'] / $c[$i - 1] - 1 : 0, 'patterns' => self::patterns($bars, $i)];
        }
        $recent = array_merge(...array_map(fn($x) => $x['patterns'], array_slice($candles, -3)));
        // Escalating: 3 higher closes in a row, the last on above-average volume.
        $escalating = $c[$n - 1] > $c[$n - 2] && $c[$n - 2] > $c[$n - 3] && $c[$n - 3] > $c[$n - 4] && $v[$n - 1] >= $volAvg;

        $signals = [
            'MA14 buy' => $ma14 !== null && $close > $ma14 && $ma14 >= $ma14Prev,
            'MACD buy' => $hist > 0 && $histPrev <= 0 || ($hist > 0 && $macd[$n - 1] > $macd[$n - 2]),
            'Hammer' => (bool) array_intersect(['Hammer (bullish)', 'Bullish engulfing'], $recent),
            'Spinning top' => in_array('Spinning top', $recent, true) || in_array('Doji', $recent, true),
            'Escalating' => $escalating,
        ];
        $bearish = (bool) array_intersect(['Shooting star (bearish)', 'Bearish engulfing', 'Bearish marubozu'], $recent);
        $techScore = $signals['MA14 buy'] * 2 + $signals['MACD buy'] * 2 + $signals['Hammer'] * 2 + $signals['Escalating'] * 1 + $signals['Spinning top'] * 0.5
            + ($rsi !== null && $rsi < 35 ? 1 : 0) - ($rsi !== null && $rsi > 72 ? 2 : 0) - ($bearish ? 2 : 0) + ($last['volume'] > 1.5 * $volAvg && $close > $c[$n - 2] ? 1 : 0);

        // Fundamentals: category, valuation, holding of sponsors.
        $pb = !empty($fund['nav']) && $fund['nav'] > 0 ? $close / $fund['nav'] : null;
        $pe = $fund['pe'] ?? null;
        $fundScore = 0;
        $notes = [];
        $cat = $fund['category'] ?? null;
        if ($cat === 'Z') { $fundScore -= 4; $notes[] = 'Z category: weak fundamentals or no dividend – avoid'; }
        elseif ($cat === 'A') { $fundScore += 2; $notes[] = 'A category: pays regular dividends'; }
        if ($pe !== null) {
            if ($pe > 0 && $pe <= 12) { $fundScore += 2; $notes[] = 'Cheap P/E ' . round($pe, 1); }
            elseif ($pe > 0 && $pe <= 20) { $fundScore += 1; $notes[] = 'Fair P/E ' . round($pe, 1); }
            else { $fundScore -= 2; $notes[] = $pe <= 0 ? 'Loss-making (negative P/E)' : 'Expensive P/E ' . round($pe, 1); }
        }
        if ($pb !== null) {
            if ($fund['nav'] < 0) { $fundScore -= 3; $notes[] = 'Negative NAV: liabilities exceed assets'; }
            elseif ($pb < 1) { $fundScore += 1; $notes[] = 'Trades below book value (P/B ' . round($pb, 2) . ')'; }
            elseif ($pb > 4) { $fundScore -= 1; $notes[] = 'High P/B ' . round($pb, 2); }
        }
        if (($fund['sponsor'] ?? 0) >= 30) { $fundScore += 1; $notes[] = 'Sponsors hold ' . $fund['sponsor'] . '%'; }
        elseif (isset($fund['sponsor']) && $fund['sponsor'] < 30) { $fundScore -= 1; $notes[] = 'Low sponsor holding ' . $fund['sponsor'] . '%'; }

        // Trade plan. Entry near support/MA14, stop below support, target near resistance (+3% min, +10% max).
        $buyLow = round(max($support, min($close, $ma14 ?? $close) - 0.5 * $atr), 1);
        $buyHigh = round($close, 1);
        $stop = round(min($buyLow - $atr, $support * 0.98), 1);
        $target = round(min(max($resistance, $close * 1.03), $close * 1.10), 1);
        $sellHigh = round(min($target * 1.03, $close * 1.12), 1);
        $risk = $buyHigh - $stop;
        $reward = $target - $buyHigh;
        // Days: target move / average true range per day, at least what the ±10% daily limit allows.
        $days = max((int) ceil(log(max($target / $close, 1.0001)) / log(1 + self::CIRCUIT)), (int) ceil($reward / max($atr * 0.5, 0.01)));
        $days = min(max($days, 3), 30);

        $zero = null;
        if ($holding && $holding['qty'] > 0) {
            $be = $holding['avg_cost'] / (1 - StockPortfolio::COMMISSION);
            $zl = $be * 1.01; // break-even + 1% net, as in the recovery plan
            $zero = ['break_even' => $be, 'zero_loss_sell' => $zl, 'rise_needed' => $zl / $close - 1,
                'circuit_days' => (int) ceil(log(max($zl / $close, 1.0001)) / log(1 + self::CIRCUIT)), 'qty' => $holding['qty'], 'avg_cost' => $holding['avg_cost']];
        }

        $total = $techScore + $fundScore;
        $verdict = match (true) {
            $cat === 'Z' || ($fund['nav'] ?? 1) < 0 => 'AVOID',
            $bearish && $techScore < 3 => 'WAIT – bearish candle',
            $techScore >= 4 && $fundScore >= 2 && $reward / max($risk, 0.01) >= 1.2 => 'BUY',
            $techScore >= 3 && $fundScore >= 0 => 'WATCH – near entry',
            default => 'WAIT',
        };

        return [
            'ok' => true, 'date' => $last['date'], 'close' => $close, 'change' => $close / $c[$n - 2] - 1,
            'ma14' => $ma14, 'macd' => $macd[$n - 1], 'signal' => $sig[$n - 1], 'hist' => $hist, 'rsi' => $rsi, 'atr' => $atr,
            'support' => $support, 'resistance' => $resistance, 'volume' => $last['volume'], 'vol_avg' => $volAvg, 'obv10' => $obvUp,
            'signals' => $signals, 'bearish' => $bearish, 'candles' => $candles,
            'tech_score' => $techScore, 'fund_score' => $fundScore, 'score' => $total, 'notes' => $notes, 'pb' => $pb, 'pe' => $pe,
            'eps' => $pe ? $close / $pe : null, 'fund' => $fund,
            'plan' => ['buy_low' => $buyLow, 'buy_high' => $buyHigh, 'stop' => $stop, 'target' => $target, 'sell_high' => $sellHigh,
                'rr' => $reward / max($risk, 0.01), 'days' => $days, 'upper_limit' => round($close * (1 + self::CIRCUIT), 1), 'lower_limit' => round($close * (1 - self::CIRCUIT), 1)],
            'zero' => $zero, 'verdict' => $verdict,
        ];
    }
}
