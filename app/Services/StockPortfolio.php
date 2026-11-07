<?php

namespace App\Services;

use App\Models\StockCash;
use App\Models\StockDividend;
use App\Models\StockSymbol;
use App\Models\StockTrade;
use Carbon\Carbon;

/**
 * DSE portfolio maths, same rules as the owner's "Stok Informations" workbook / LBSL:
 * average cost incl. buy commission, realized P/L = sell proceeds after commission − avg cost,
 * unrealized after a 0.40% sell commission, shares sellable after T+N working days (Fri/Sat off).
 */
class StockPortfolio
{
    public const COMMISSION = 0.004;

    // Working days after the trade before shares can be sold; Spot market buys settle T+1.
    public const SETTLEMENT_DAYS = ['A' => 2, 'B' => 2, 'G' => 2, 'N' => 2, 'Z' => 3];

    // Ledger categories the unrealized row moves between (STOCK INCOME FROM DSE / STOCK UNREALIZE LOSS FROM DSE).
    public const LEDGER_GAIN = 26;
    public const LEDGER_LOSS = 34;

    public static function load(?string $today = null): array
    {
        $trades = StockTrade::orderBy('date')->orderBy('id')->get()
            ->map(fn($t) => ['id' => $t->id, 'date' => $t->date->toDateString(), 'side' => $t->side, 'symbol' => $t->symbol,
                'market' => $t->market, 'qty' => $t->qty, 'rate' => $t->rate, 'commission' => $t->commission])->all();
        $symbols = StockSymbol::all()->keyBy('symbol')->map(fn($s) => $s->only('company', 'category', 'price', 'prev_close', 'price_date'))->all();
        $cash = StockCash::orderBy('date')->get(['type', 'cash_in', 'cash_out'])->toArray();
        $dividends = StockDividend::get(['status', 'gross', 'net'])->toArray();

        return self::compute($trades, $symbols, $cash, $dividends, $today ?? now()->toDateString());
    }

    /** Pure: plain arrays in, every portfolio figure out. Trades must be in date order. */
    public static function compute(array $trades, array $symbols, array $cash, array $dividends, string $today): array
    {
        $pos = [];
        $rows = [];
        foreach ($trades as $t) {
            $p = &$pos[$t['symbol']];
            $p ??= ['qty' => 0, 'cost' => 0.0, 'lots' => [], 'buy_qty' => 0, 'buy_cost' => 0.0, 'sell_qty' => 0, 'sell_net' => 0.0, 'commission' => 0.0, 'realized' => 0.0];
            $amount = $t['qty'] * $t['rate'];
            $p['commission'] += $t['commission'];
            $t['amount'] = $amount;
            $t['realized'] = null;
            if ($t['side'] === 'Buy') {
                $t['net'] = $amount + $t['commission'];
                $p['qty'] += $t['qty'];
                $p['cost'] += $t['net'];
                $p['buy_qty'] += $t['qty'];
                $p['buy_cost'] += $t['net'];
                $category = $symbols[$t['symbol']]['category'] ?? 'A';
                $days = $t['market'] === 'Spot' ? 1 : (self::SETTLEMENT_DAYS[$category] ?? 2);
                $p['lots'][] = ['qty' => $t['qty'], 'date' => $t['date'], 'mature' => self::addWorkingDays($t['date'], $days)];
            } else {
                $t['net'] = $amount - $t['commission'];
                $soldCost = $p['qty'] > 0 ? $p['cost'] / $p['qty'] * min($t['qty'], $p['qty']) : 0.0;
                $t['realized'] = $t['net'] - $soldCost;
                $p['qty'] -= $t['qty'];
                $p['cost'] = $p['qty'] > 0 ? $p['cost'] - $soldCost : 0.0;
                $p['sell_qty'] += $t['qty'];
                $p['sell_net'] += $t['net'];
                $p['realized'] += $t['realized'];
                // Maturity is tracked per lot, oldest sold first.
                for ($left = $t['qty']; $left > 0 && $p['lots'];) {
                    $take = min($left, $p['lots'][0]['qty']);
                    $left -= $take;
                    $p['lots'][0]['qty'] -= $take;
                    if ($p['lots'][0]['qty'] <= 0) array_shift($p['lots']);
                }
            }
            $rows[] = $t;
            unset($p);
        }

        $holdings = [];
        $realized = [];
        foreach ($pos as $symbol => $p) {
            $s = $symbols[$symbol] ?? [];
            $realized[] = ['symbol' => $symbol, 'company' => $s['company'] ?? null, 'open' => $p['qty'] > 0] + array_intersect_key($p, array_flip(['buy_qty', 'buy_cost', 'sell_qty', 'sell_net', 'commission', 'realized']));
            if ($p['qty'] <= 0) continue;

            $price = $s['price'] ?? null;
            $value = $price !== null ? $price * $p['qty'] : null;
            $sellComm = $value !== null ? $value * self::COMMISSION : null;
            $immature = array_filter($p['lots'], fn($l) => $l['mature'] > $today);
            $immatureQty = array_sum(array_column($immature, 'qty'));
            $heldDays = array_sum(array_map(fn($l) => $l['qty'] * Carbon::parse($l['date'])->diffInDays($today), $p['lots']));
            $holdings[] = [
                'symbol' => $symbol, 'company' => $s['company'] ?? null, 'category' => $s['category'] ?? '?',
                'qty' => $p['qty'], 'sellable' => $p['qty'] - $immatureQty, 'immature' => $immatureQty,
                'next_mature' => $immature ? min(array_column($immature, 'mature')) : null,
                'avg_cost' => $p['cost'] / $p['qty'], 'cost' => $p['cost'], 'price' => $price, 'value' => $value,
                'net_if_sold' => $value !== null ? $value - $sellComm : null,
                'unrealized' => $value !== null ? $value - $sellComm - $p['cost'] : null,
                'unrealized_pct' => $value !== null && $p['cost'] > 0 ? ($value - $sellComm - $p['cost']) / $p['cost'] : null,
                // Price at which selling everything (after sell commission) gets the cost back.
                'break_even' => $p['cost'] / $p['qty'] / (1 - self::COMMISSION),
                'day_change_pct' => $price !== null && !empty($s['prev_close']) ? ($price - $s['prev_close']) / $s['prev_close'] : null,
                'avg_days' => $p['qty'] ? $heldDays / $p['qty'] : 0,
                'oldest_buy' => $p['lots'] ? $p['lots'][0]['date'] : null,
            ];
        }
        usort($holdings, fn($a, $b) => strcmp($a['symbol'], $b['symbol']));
        usort($realized, fn($a, $b) => strcmp($a['symbol'], $b['symbol']));

        $cashBy = fn(string $type, string $col) => array_sum(array_map(fn($c) => $c['type'] === $type ? (float) $c[$col] : 0, $cash));
        $buy = array_sum(array_map(fn($t) => $t['side'] === 'Buy' ? $t['net'] : 0, $rows));
        $sell = array_sum(array_map(fn($t) => $t['side'] === 'Sell' ? $t['net'] : 0, $rows));
        $cashIn = array_sum(array_column($cash, 'cash_in'));
        $cashOut = array_sum(array_column($cash, 'cash_out'));
        $cost = array_sum(array_column($holdings, 'cost'));
        $priced = array_filter($holdings, fn($h) => $h['value'] !== null);
        $value = array_sum(array_column($priced, 'value'));
        $unrealized = array_sum(array_column($priced, 'unrealized'));
        $deposit = $cashBy('Deposit', 'cash_in');
        $withdraw = $cashBy('Withdraw', 'cash_out');
        $capital = $deposit - $withdraw;
        $cashBalance = $cashIn - $cashOut - $buy + $sell;
        $equity = $cashBalance + $value;

        return [
            'today' => $today,
            'holdings' => $holdings,
            'realized' => $realized,
            'trades' => $rows,
            'totals' => [
                'deposit' => $deposit, 'withdraw' => $withdraw, 'fees' => $cashBy('Fee', 'cash_out'),
                'dividends_received' => $cashBy('Dividend', 'cash_in'),
                'dividends_receivable' => array_sum(array_map(fn($d) => $d['status'] === 'Receivable' ? (float) $d['gross'] : 0, $dividends)),
                'capital' => $capital, 'buy' => $buy, 'sell' => $sell,
                'commission' => array_sum(array_column($rows, 'commission')),
                'realized' => array_sum(array_column($realized, 'realized')),
                'cash_balance' => $cashBalance, 'cost' => $cost, 'value' => $value, 'unrealized' => $unrealized,
                'unrealized_pct' => $cost > 0 ? $unrealized / $cost : 0,
                'unpriced' => count($holdings) - count($priced),
                'equity' => $equity, 'gain' => $equity - $capital, 'return_pct' => $capital > 0 ? ($equity - $capital) / $capital : 0,
                'shares' => array_sum(array_column($holdings, 'qty')),
                'sellable' => array_sum(array_column($holdings, 'sellable')),
                'immature' => array_sum(array_column($holdings, 'immature')),
            ],
        ];
    }

    /**
     * Keep the single "UNREALIZED …" ledger row (and its linked INVESTMENT entry) equal to today's
     * unrealized P/L: a loss sits in STOCK UNREALIZE LOSS / Withdraws, a gain in STOCK INCOME / Savings.
     * Called after every price or trade change. Returns the ledger row id, or null when there is none.
     */
    public static function syncUnrealizedLedger(): ?int
    {
        $row = \App\Models\ExpenseCalculation::where('name', 'like', 'UNREALIZED %')->whereIn('category_id', [self::LEDGER_GAIN, self::LEDGER_LOSS])->first();
        if (!$row) return null;

        $p = self::load();
        $value = round($p['totals']['unrealized'], 2);
        $gain = $value > 0;
        $oldDate = $row->date;
        $row->fill([
            'category_id' => $gain ? self::LEDGER_GAIN : self::LEDGER_LOSS,
            'types' => $gain ? 'INCOME' : 'EXPENSE',
            'rules' => $gain ? '0' : 'SAVINGS', // as the two categories are set up
            'amount' => (string) abs($value),
            'date' => $p['today'],
            'name' => 'UNREALIZED ' . ($gain ? 'GAIN' : 'LOSS') . ' UP TO ' . strtoupper(\Carbon\Carbon::parse($p['today'])->format('d-M-Y')) . ' (LBSL, AFTER SELL COMMISSION)',
        ])->save(); // the model hook copies name/date/amount to the linked INVESTMENT entry
        \App\Models\HandCash::where('expense_calculation_id', $row->id)->update(['types' => $gain ? 'SAVE' : 'WIDROWS']);
        \App\Models\ExpenseCalculation::flushReportCaches(array_filter([$oldDate, $p['today']]));

        return $row->id;
    }

    /**
     * Fetch today's price and yesterday's close from each company page on dse.com.bd (the old dsebd.org
     * pages are retired) and save them. Returns [updated symbols, failed symbols].
     */
    public static function fetchDsePrices(?array $symbols = null): array
    {
        $symbols ??= array_column(self::load()['holdings'], 'symbol');
        $updated = [];
        $failed = [];
        foreach ($symbols as $symbol) {
            try {
                $r = \Illuminate\Support\Facades\Http::timeout(20)->retry(2, 1000)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (PCM personal portfolio; daily price check)'])
                    ->get('https://www.dse.com.bd/company/' . rawurlencode($symbol));
                $quote = $r->successful() ? self::parseDsePrice($r->body()) : null;
            } catch (\Throwable $e) {
                $quote = null;
            }
            if (!$quote) {
                $failed[] = $symbol;
                continue;
            }
            StockSymbol::where('symbol', $symbol)->update(['price' => $quote['price'], 'prev_close' => $quote['prev_close'], 'price_date' => now()->toDateString()]);
            $updated[$symbol] = $quote;
        }
        if ($updated) self::syncUnrealizedLedger();

        return [$updated, $failed];
    }

    /** "BDT 230.20 ▲ 1.20 (+0.52%) today Closing BDT 230.20 Yesterday's close BDT 229.00" → price 230.20, prev_close 229.00. */
    public static function parseDsePrice(string $html): ?array
    {
        $text = html_entity_decode(strip_tags(preg_replace('#<(script|style)\b[^>]*>.*?</\1>#si', '', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        if (!preg_match("/BDT\s*([\d,]+(?:\.\d+)?)\s*[▲▼]?.{0,60}?Closing\s*BDT\s*[\d,.]+\s*Yesterday['’]s close\s*BDT\s*([\d,]+(?:\.\d+)?)/u", $text, $m)) {
            return null;
        }
        [$price, $prev] = [(float) str_replace(',', '', $m[1]), (float) str_replace(',', '', $m[2])];

        return $price > 0 ? ['price' => $price, 'prev_close' => $prev > 0 ? $prev : null] : null;
    }

    /**
     * Pure: figures for the AI and rule-based recommendations for the Stock Portfolio page.
     * Every number in the text is one of the (rounded) metrics, so the AI check accepts them.
     */
    public static function advice(array $p): array
    {
        $t = $p['totals'];
        $pct = fn($v) => round($v * 100, 1);
        $holdings = array_map(fn($h) => ['symbol' => $h['symbol'], 'category' => $h['category'], 'cost' => round($h['cost']),
            'market_value' => $h['value'] === null ? null : round($h['value']), 'weight_percent' => $t['value'] > 0 && $h['value'] !== null ? $pct($h['value'] / $t['value']) : null,
            'unrealized_percent' => $h['unrealized_pct'] === null ? null : $pct($h['unrealized_pct']), 'price' => $h['price'], 'break_even_price' => round($h['break_even'], 2)], $p['holdings']);
        $m = ['equity' => round($t['equity']), 'capital_invested' => round($t['capital']), 'gain_vs_capital' => round($t['gain']), 'return_percent' => $pct($t['return_pct']),
            'realized_profit' => round($t['realized']), 'unrealized' => round($t['unrealized']), 'unrealized_percent' => $pct($t['unrealized_pct']),
            'cash_in_bo_account' => round($t['cash_balance']), 'cash_percent_of_equity' => $t['equity'] > 0 ? $pct($t['cash_balance'] / $t['equity']) : 0,
            'dividends_receivable' => round($t['dividends_receivable']), 'shares_not_yet_sellable' => $t['immature'],
            'holdings_count' => count($holdings), 'holdings_below_cost' => count(array_filter($holdings, fn($h) => $h['unrealized_percent'] !== null && $h['unrealized_percent'] < 0)),
            'holdings' => $holdings];

        $f = fn($v) => number_format($v);
        $ins = [sprintf('Total equity is %s against %s invested, a %s of %s (%s%%).', $f($m['equity']), $f($m['capital_invested']), $m['gain_vs_capital'] < 0 ? 'loss' : 'gain', $f(abs($m['gain_vs_capital'])), abs($m['return_percent'])),
            sprintf('Closed trades made %s; the open positions are at %s (%s%%) after sell commission.', $f($m['realized_profit']), $f($m['unrealized']), $m['unrealized_percent'])];
        $act = [];
        $priced = array_filter($holdings, fn($h) => $h['weight_percent'] !== null);
        if ($priced) {
            $top = array_reduce($priced, fn($a, $h) => $a === null || $h['weight_percent'] > $a['weight_percent'] ? $h : $a);
            $ins[] = sprintf('%s is the largest holding at %s%% of market value.', $top['symbol'], $top['weight_percent']);
            if ($top['weight_percent'] >= 40) $act[] = sprintf('%s is %s%% of the portfolio: avoid adding more to it until the other holdings catch up.', $top['symbol'], $top['weight_percent']);
        }
        $under = array_filter($holdings, fn($h) => $h['unrealized_percent'] !== null && $h['unrealized_percent'] < 0);
        if ($under) {
            $worst = array_reduce($under, fn($a, $h) => $a === null || $h['unrealized_percent'] < $a['unrealized_percent'] ? $h : $a);
            $ins[] = sprintf('%d of %d holdings trade below cost; the deepest is %s at %s%% (break-even %s).', count($under), count($holdings), $worst['symbol'], $worst['unrealized_percent'], $worst['break_even_price']);
            $act[] = sprintf('Review %s: it needs %s per share just to break even; decide whether to hold or cut it.', $worst['symbol'], $worst['break_even_price']);
        }
        $ins[] = sprintf('Cash in the BO account is %s (%s%% of equity).', $f($m['cash_in_bo_account']), $m['cash_percent_of_equity']);
        if ($m['cash_percent_of_equity'] >= 40) $act[] = sprintf('%s%% of the account is idle cash: invest it gradually or move part to a deposit.', $m['cash_percent_of_equity']);
        if ($m['dividends_receivable'] > 0) $act[] = sprintf('Follow up the %s of dividends still receivable.', $f($m['dividends_receivable']));
        if (!$act) $act[] = 'Fetch DSE prices after market close so these figures stay current.';

        return ['metrics' => $m, 'template' => ['insights' => array_slice($ins, 0, 5), 'actions' => array_slice($act, 0, 3)]];
    }

    // shortcut: skips Fri/Sat only, not DSE public holidays; add a holiday list if maturity dates drift around Eid.
    public static function addWorkingDays(string $date, int $days): string
    {
        $d = Carbon::parse($date);
        while ($days > 0) {
            $d->addDay();
            if (!in_array($d->dayOfWeekIso, [5, 6], true)) $days--;
        }

        return $d->toDateString();
    }
}
