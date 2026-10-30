<?php

namespace App\Services\Analytics;

use Carbon\Carbon;

/**
 * Recurring commitments, hand-cash runway, Monte Carlo savings and Eid sinking funds.
 * Pure functions over plain arrays; Monte Carlo uses 5,000 paths with a fixed seed so reruns match.
 */
final class CashFlow
{
    public const PATHS = 5000;
    private const CADENCES = ['monthly' => [25, 35], 'quarterly' => [80, 100], 'yearly' => [350, 380]];
    private const MONTH_WORDS = 'JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER|JAN|FEB|MAR|APR|JUN|JUL|AUG|SEPT|SEP|OCT|NOV|DEC';

    /**
     * Stable name for a repeating payment: keeps account numbers (6+ digits) but drops what
     * changes each time - ordinals ('7TH'), month names, amounts and punctuation.
     * '1783010022664 DPS 7TH MONTH' -> '1783010022664 DPS MONTH'; 'INTERNET BILL PAYMENT JULY' -> 'INTERNET BILL PAYMENT'.
     */
    public static function key(?string $name): string
    {
        $s = strtoupper((string) $name);
        $s = preg_replace('/\b\d+(ST|ND|RD|TH)\b/', ' ', $s);
        $s = preg_replace('/\b(' . self::MONTH_WORDS . ')\b/', ' ', $s);
        $s = preg_replace('/(?<!\d)\d{1,5}(?!\d)/', ' ', $s);
        $s = preg_replace('/[^A-Z0-9 ]/', ' ', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /**
     * rows: [['src', 'group', 'name', 'date', 'amount'], ...] -> regular payments with next due date.
     * Recurring = 4+ payments in 4+ months, a steady gap (CV < 0.35) matching a monthly/quarterly/
     * yearly cadence, a steady amount (CV < 0.3), and still active (last payment within 1.5 gaps).
     */
    public static function recurring(array $rows, string $today): array
    {
        $today = Carbon::parse($today);
        $groups = [];
        foreach ($rows as $r) {
            $k = self::key($r['name']);
            if ($k !== '') {
                // Several payments on one day count as one payment.
                $g = &$groups[$r['src'] . "\0" . $r['group'] . "\0" . $k];
                $g[$r['date']] = ($g[$r['date']] ?? 0) + (float) $r['amount'];
                unset($g);
            }
        }
        $out = [];
        foreach ($groups as $gk => $byDay) {
            [$src, $group, $name] = explode("\0", $gk);
            ksort($byDay);
            $days = array_keys($byDay);
            if (count($days) < 4 || count(array_unique(array_map(fn($d) => substr($d, 0, 7), $days))) < 4) {
                continue;
            }
            $gaps = [];
            for ($i = 1; $i < count($days); $i++) {
                $gaps[] = Carbon::parse($days[$i - 1])->diffInDays(Carbon::parse($days[$i]));
            }
            $gap = TimeSeries::median($gaps);
            $cadence = null;
            foreach (self::CADENCES as $c => [$lo, $hi]) {
                if ($gap >= $lo && $gap <= $hi) {
                    $cadence = $c;
                }
            }
            $amounts = array_values($byDay);
            $last = Carbon::parse(end($days));
            if (!$cadence || self::cv($gaps) >= 0.35 || self::cv($amounts) >= 0.3 || $last->diffInDays($today) > 1.5 * $gap) {
                continue;
            }
            $step = (int) round($gap);
            $next = $last->copy()->addDays($step);
            while ($next->lt($today)) {
                $next->addDays($step);
            }
            $calendar = [];
            for ($d = $next->copy(); $d->lt($today->copy()->addDays(365)); $d->addDays($step)) {
                $calendar[] = $d->toDateString();
            }
            $amount = TimeSeries::median(array_slice($amounts, -3));
            $out[] = ['src' => $src, 'group' => $group, 'name' => $name, 'cadence' => $cadence, 'count' => count($days),
                'median_gap_days' => $gap, 'amount' => $amount, 'last_date' => $last->toDateString(),
                'next_date' => $next->toDateString(), 'monthly_equivalent' => $amount * 30.44 / $gap, 'calendar' => $calendar];
        }
        usort($out, fn($a, $b) => $a['next_date'] <=> $b['next_date']);
        return $out;
    }

    /**
     * $burn: cash spent from hand per day (oldest first, zero-filled, transfers excluded).
     * Bootstraps 7-day blocks of the last 180 days (keeps the weekday pattern) to simulate how
     * long the current cash lasts; $withdrawals: [[date, amount], ...] for the habit summary.
     */
    public static function handCash(array $burn, float $balance, array $withdrawals): array
    {
        mt_srand(Engine::SEED);
        $recent = array_slice($burn, -180);
        $blocks = [];
        for ($i = 0; $i + 7 <= count($recent); $i++) {
            $blocks[] = array_slice($recent, $i, 7);
        }
        $horizon = 182;
        $empty = $need7 = $need30 = $week = [];
        for ($p = 0; $p < self::PATHS; $p++) {
            $cum = 0;
            $day = 0;
            $emptyAt = $horizon + 1;
            while ($day < $horizon) {
                foreach ($blocks[mt_rand(0, count($blocks) - 1)] as $v) {
                    $cum += $v;
                    $day++;
                    if ($day === 7) {
                        $week[] = $cum;
                    }
                    if ($day === 30) {
                        $need30[] = $cum;
                    }
                    if ($cum >= $balance && $emptyAt > $horizon) {
                        $emptyAt = $day;
                    }
                }
            }
            $empty[] = $emptyAt;
        }
        $q = fn($x, $p) => TimeSeries::quantile($x, $p);
        $out = [
            'balance' => $balance,
            'avg_daily_burn_90d' => array_sum(array_slice($burn, -90)) / min(90, count($burn)),
            'avg_daily_burn_180d' => array_sum($recent) / count($recent),
            'days_until_empty' => ['p10' => (int) $q($empty, 0.1), 'p50' => (int) $q($empty, 0.5), 'p90' => (int) $q($empty, 0.9)],
            'prob_empty_within' => array_combine([7, 14, 30], array_map(fn($d) => count(array_filter($empty, fn($e) => $e <= $d)) / self::PATHS, [7, 14, 30])),
            'spend_next_30d' => ['p50' => $q($need30, 0.5), 'p80' => $q($need30, 0.8), 'p95' => $q($need30, 0.95)],
            'paths' => self::PATHS,
        ];
        // Withdraw enough to cover a week / 30 days at the 80th-percentile spend; top up again when
        // cash drops below what a bad week (90th percentile of 7-day spend) would need.
        $topUp = fn($need) => max(0, ceil(($need - $balance) / 500) * 500);
        $out['suggested_withdrawal'] = ['7' => $topUp($q($week, 0.8)), '30' => $topUp($out['spend_next_30d']['p80'])];
        $out['reorder_point'] = ceil($q($week, 0.9) / 100) * 100;
        if (count($withdrawals) >= 2) {
            usort($withdrawals, fn($a, $b) => $a[0] <=> $b[0]);
            $gaps = [];
            for ($i = 1; $i < count($withdrawals); $i++) {
                $gaps[] = Carbon::parse($withdrawals[$i - 1][0])->diffInDays(Carbon::parse($withdrawals[$i][0]));
            }
            $out['history'] = ['median_withdrawal' => TimeSeries::median(array_column($withdrawals, 1)), 'median_days_between' => TimeSeries::median($gaps)];
        }
        return $out;
    }

    /**
     * Monte Carlo over bootstrapped months (last 24 completed):
     *  - cumulative net savings quantiles for 1..24 months ahead (the chance of any goal is read from these)
     *  - emergency runway: months the liquid balance lasts if income stops.
     */
    public static function savings(array $net, array $spend, float $liquid): array
    {
        mt_srand(Engine::SEED);
        $net = array_values(array_slice($net, -24));
        $spend = array_values(array_slice($spend, -24));
        $byHorizon = array_fill(0, 24, []);
        $months = [];
        for ($p = 0; $p < self::PATHS; $p++) {
            $cum = 0;
            for ($h = 0; $h < 24; $h++) {
                $cum += $net[mt_rand(0, count($net) - 1)];
                $byHorizon[$h][] = $cum;
            }
            $burn = 0;
            $m = 121;
            for ($i = 1; $i <= 120; $i++) {
                $burn += $spend[mt_rand(0, count($spend) - 1)];
                if ($burn >= $liquid) {
                    $m = $i;
                    break;
                }
            }
            $months[] = $m;
        }
        $qs = range(5, 95, 5);
        $median = TimeSeries::median($spend);
        return [
            'quantiles' => $qs,
            'cumulative_net' => array_map(fn($vals) => array_map(fn($q) => TimeSeries::quantile($vals, $q / 100), $qs), $byHorizon),
            'liquid' => $liquid,
            'median_monthly_spend' => $median,
            'emergency_months_simple' => $median > 0 ? $liquid / $median : null,
            'emergency_months' => ['p10' => (int) TimeSeries::quantile($months, 0.1), 'p50' => (int) TimeSeries::quantile($months, 0.5), 'p90' => (int) TimeSeries::quantile($months, 0.9)],
            'paths' => self::PATHS,
        ];
    }

    /**
     * Extra spend in Eid / Ramadan months vs the median of non-event months within ±6 months,
     * and the monthly saving needed to cover the next one.
     * $flags: [ramadan_days, eid_fitr, eid_adha] per month; $ahead: [[ym, flags], ...] from this month on.
     */
    public static function events(array $months, array $spend, array $flags, array $ahead): array
    {
        $kinds = ['Ramadan' => fn($f) => $f[0] >= 15, 'Eid-ul-Fitr' => fn($f) => $f[1] > 0, 'Eid-ul-Adha' => fn($f) => $f[2] > 0];
        $anyEvent = array_map(fn($f) => $kinds['Ramadan']($f) || $kinds['Eid-ul-Fitr']($f) || $kinds['Eid-ul-Adha']($f), $flags);
        $out = [];
        foreach ($kinds as $name => $isEvent) {
            $past = [];
            foreach ($flags as $i => $f) {
                if ($isEvent($f)) {
                    $near = [];
                    for ($j = max(0, $i - 6); $j < min(count($spend), $i + 7); $j++) {
                        if (!$anyEvent[$j]) {
                            $near[] = $spend[$j];
                        }
                    }
                    $base = TimeSeries::median($near);
                    $past[] = ['month' => $months[$i], 'spend' => $spend[$i], 'normal' => $base, 'extra' => $spend[$i] - $base];
                }
            }
            $next = null;
            foreach ($ahead as $i => [, $f]) {
                if ($isEvent($f)) {
                    $next = $i;
                    break;
                }
            }
            $extra = $past ? TimeSeries::median(array_column($past, 'extra')) : null;
            $left = $next !== null ? $next + 1 : null; // this month counts as one to save in
            $out[] = ['event' => $name, 'past' => $past, 'median_extra' => $extra, 'next_month' => $next !== null ? $ahead[$next][0] : null,
                'months_left' => $left, 'monthly_saving' => $extra !== null && $left ? max($extra, 0) / $left : null];
        }
        return $out;
    }

    /** Coefficient of variation (population SD / mean). */
    private static function cv(array $x): float
    {
        $m = array_sum($x) / count($x);
        return $m > 0 ? sqrt(array_sum(array_map(fn($v) => ($v - $m) ** 2, $x)) / count($x)) / $m : INF;
    }
}
