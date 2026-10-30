<?php

namespace App\Services\Analytics;

use Carbon\Carbon;
use IntlCalendar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clean monthly series from the two ledgers, shared by the analytic reports.
 *  - amount is VARCHAR and may hold "5,315", so it is always summed comma-safe (AMOUNT)
 *  - rows dated after today are left out (a 2027 placeholder row exists)
 *  - account transfers only live in hand_cashes (transfer_pair_id), never in the
 *    income/expense ledger, so they cannot inflate spending
 *  - spend is split by the row's rule: needs + wants = "core" spend (minus one-offs);
 *    SAVINGS-rule rows and rule "0" (stock commission) are kept in their own buckets,
 *    so `expense` still equals what the existing reports call total expense
 */
class LedgerSeries
{
    public const AMOUNT = "CAST(REPLACE(amount, ',', '') AS DECIMAL(15,2))";
    public const CASH_ACCOUNT = 'PETI';
    // shortcut: account roles are inferred from their names (DPS/FD/INVESTMENT locked, LOAN/CREDITCARD debts); confirm and adjust here.
    public const LIQUID_ACCOUNTS = ['PETI', 'CASH', 'CITY_BANK', 'CITY_BANK_ISLAMIC', 'MOBILE_BKASH', 'MOBILE_ROCKET', 'SONALI_BANK_GULSHAN', 'SONALI_BANK_TONGI', 'DBBL', 'PBL'];

    private static ?array $oneOffIds = null;

    public static function cutoff(): string
    {
        return now()->toDateString();
    }

    public static function firstDate(): string
    {
        return (string) DB::table('expense_calculations')->min('date');
    }

    /** Every 'Y-m' from $from to $to inclusive. */
    public static function months(string $from, string $to): array
    {
        $out = [];
        for ($c = Carbon::parse($from)->startOfMonth(), $end = Carbon::parse($to)->startOfMonth(); $c->lte($end); $c->addMonth()) {
            $out[] = $c->format('Y-m');
        }
        return $out;
    }

    /** Ledger totals per month, zero-filled, with calendar flags. */
    public static function monthly(?string $from = null, ?string $to = null): array
    {
        $from ??= self::firstDate();
        $to = min($to ?? self::cutoff(), self::cutoff());
        $oneOff = array_flip(self::oneOffIds());
        $blank = ['income' => 0, 'expense' => 0, 'needs' => 0, 'wants' => 0, 'savings' => 0, 'other' => 0, 'core' => 0, 'one_off' => 0, 'rows' => 0];
        $out = array_fill_keys(self::months($from, $to), $blank);

        $rows = DB::table('expense_calculations')->whereBetween('date', [$from, $to])
            ->selectRaw("id, DATE_FORMAT(date, '%Y-%m') ym, types, rules, " . self::AMOUNT . ' amt')->get();
        foreach ($rows as $r) {
            $m = &$out[$r->ym];
            $a = (float) $r->amt;
            $m['rows']++;
            if ($r->types === 'INCOME') {
                $m['income'] += $a;
                continue;
            }
            $m['expense'] += $a;
            $bucket = ['NEEDS' => 'needs', 'WANTS' => 'wants', 'SAVINGS' => 'savings'][$r->rules] ?? 'other';
            $m[$bucket] += $a;
            if ($bucket === 'needs' || $bucket === 'wants') {
                $m[isset($oneOff[$r->id]) ? 'one_off' : 'core'] += $a;
            }
        }
        unset($m);

        foreach ($out as $ym => $m) {
            $out[$ym] = array_map(fn($v) => round($v, 2), $m) + self::monthFlags($ym);
        }
        return $out;
    }

    /**
     * Per-category monthly totals, zero-filled. `true_zero` is false for a month in which
     * the ledger has no rows at all (nothing was recorded, so a 0 there is unknown, not real).
     */
    public static function categoryMonthly(?string $from = null, ?string $to = null): array
    {
        $from ??= self::firstDate();
        $to = min($to ?? self::cutoff(), self::cutoff());
        $months = self::months($from, $to);
        $oneOff = array_flip(self::oneOffIds());

        $rows = DB::table('expense_calculations')->whereBetween('date', [$from, $to])
            ->selectRaw("id, category_id, DATE_FORMAT(date, '%Y-%m') ym, " . self::AMOUNT . ' amt')->get();
        $recorded = array_fill_keys($rows->pluck('ym')->unique()->all(), true);

        $out = [];
        foreach (DB::table('categories')->orderBy('id')->get(['id', 'name', 'types', 'rules']) as $c) {
            $out[$c->id] = ['name' => $c->name, 'type' => $c->types, 'rule' => $c->rules, 'months' => array_fill_keys($months, 0.0), 'one_off' => array_fill_keys($months, 0.0)];
        }
        foreach ($rows as $r) {
            $out[$r->category_id]['months'][$r->ym] += (float) $r->amt;
            if (isset($oneOff[$r->id])) {
                $out[$r->category_id]['one_off'][$r->ym] += (float) $r->amt;
            }
        }
        $trueZero = [];
        foreach ($months as $ym) {
            $trueZero[$ym] = isset($recorded[$ym]);
        }
        return ['categories' => $out, 'true_zero' => $trueZero];
    }

    /**
     * Hand-cash ledger per account per month: inflow/outflow split into real money vs
     * transfers between own accounts, with opening and closing balance (SAVE in, WIDROWS out).
     */
    public static function accounts(?string $from = null, ?string $to = null): array
    {
        $from ??= self::firstDate();
        $to = min($to ?? self::cutoff(), self::cutoff());
        $months = self::months($from, $to);

        $rows = DB::table('hand_cashes')->where('date', '<=', $to)
            ->selectRaw("rules account, LEFT(date, 7) ym, types, transfer_pair_id IS NOT NULL is_transfer, SUM(" . self::AMOUNT . ') amt')
            ->groupBy('account', 'ym', 'types', 'is_transfer')->get();

        $out = [];
        foreach ($rows as $r) {
            $acc = &$out[$r->account];
            $acc ??= ['opening' => 0.0, 'months' => array_fill_keys($months, ['in' => 0.0, 'out' => 0.0, 'transfer_in' => 0.0, 'transfer_out' => 0.0])];
            $sign = $r->types === 'SAVE' ? 1 : -1;
            if ($r->ym < $months[0]) {
                $acc['opening'] += $sign * $r->amt;
                continue;
            }
            $key = ($r->is_transfer ? 'transfer_' : '') . ($sign > 0 ? 'in' : 'out');
            $acc['months'][$r->ym][$key] += (float) $r->amt;
        }
        unset($acc);

        foreach ($out as $name => $acc) {
            $bal = round($acc['opening'], 2);
            foreach ($acc['months'] as $ym => $m) {
                $m['opening'] = $bal;
                $bal = round($bal + $m['in'] + $m['transfer_in'] - $m['out'] - $m['transfer_out'], 2);
                $out[$name]['months'][$ym] = $m + ['closing' => $bal];
            }
            $out[$name]['closing'] = $bal;
        }
        ksort($out);
        return $out;
    }

    /** Ledger ids treated as one-off: auto-detected, then manual overrides win. */
    public static function oneOffIds(): array
    {
        if (self::$oneOffIds !== null) {
            return self::$oneOffIds;
        }
        $rows = DB::table('expense_calculations')->where('types', 'EXPENSE')->whereIn('rules', ['NEEDS', 'WANTS'])
            ->where('date', '<=', self::cutoff())
            ->selectRaw("id, category_id cat, DATE_FORMAT(date, '%Y-%m') ym, " . self::AMOUNT . ' amount')
            ->get()->map(fn($r) => (array) $r)->all();
        $ids = array_flip(self::detectOneOffs($rows));

        if (Schema::hasTable('analytics_one_off_overrides')) {
            foreach (DB::table('analytics_one_off_overrides')->get(['expense_calculation_id', 'is_one_off']) as $o) {
                if ($o->is_one_off) {
                    $ids[$o->expense_calculation_id] = true;
                } else {
                    unset($ids[$o->expense_calculation_id]);
                }
            }
        }
        return self::$oneOffIds = array_keys($ids);
    }

    /**
     * A transaction is one-off when it is a robust outlier within its category
     * (MAD z-score > 5, or > 3x the median when MAD is 0), is large relative to a normal
     * month (>= 25% of the median monthly spend), and no other month of the category has a similar
     * amount (±10%), which would make it a repeating bill instead.
     * $rows: [['id', 'cat', 'ym', 'amount'], ...]
     */
    public static function detectOneOffs(array $rows): array
    {
        $byMonth = [];
        $byCat = [];
        foreach ($rows as $r) {
            $byMonth[$r['ym']] = ($byMonth[$r['ym']] ?? 0) + $r['amount'];
            $byCat[$r['cat']][] = $r;
        }
        if (!$byMonth) {
            return [];
        }
        $floor = 0.25 * TimeSeries::median(array_values($byMonth));

        $ids = [];
        foreach ($byCat as $list) {
            if (count($list) < 5) {
                continue; // too few rows to call anything unusual
            }
            $amounts = array_column($list, 'amount');
            $med = TimeSeries::median($amounts);
            $mad = TimeSeries::mad($amounts);
            foreach ($list as $r) {
                $a = $r['amount'];
                $outlier = $mad > 0 ? ($a - $med) / $mad > 5 : $a > 3 * $med;
                if (!$outlier || $a < $floor) {
                    continue;
                }
                $similarMonths = [];
                foreach ($list as $o) {
                    if ($o['ym'] !== $r['ym'] && abs($o['amount'] - $a) <= 0.1 * $a) {
                        $similarMonths[$o['ym']] = true;
                    }
                }
                if (!$similarMonths) {
                    $ids[] = $r['id'];
                }
            }
        }
        return $ids;
    }

    /**
     * Bangladesh calendar events for a 'Y-m' month: Ramadan days, Eid-ul-Fitr (1 Shawwal)
     * and Eid-ul-Adha (10 Dhul-Hijjah), which move ~11 days earlier every Gregorian year.
     * shortcut: Umm al-Qura is the Saudi calendar; Bangladesh moon sighting is often 1 day later. Add a date table if that day matters.
     */
    public static function monthFlags(string $ym): array
    {
        static $cal;
        $cal ??= IntlCalendar::createInstance('UTC', '@calendar=islamic-umalqura');
        $f = ['ramadan_days' => 0, 'eid_fitr' => false, 'eid_adha' => false];
        for ($d = Carbon::parse("$ym-01", 'UTC'), $end = $d->copy()->endOfMonth(); $d->lte($end); $d->addDay()) {
            $cal->setTime($d->getTimestamp() * 1000);
            $hm = $cal->get(IntlCalendar::FIELD_MONTH); // 0 = Muharram
            $hd = $cal->get(IntlCalendar::FIELD_DAY_OF_MONTH);
            $f['ramadan_days'] += $hm === 8 ? 1 : 0;
            $f['eid_fitr'] = $f['eid_fitr'] || ($hm === 9 && $hd === 1);
            $f['eid_adha'] = $f['eid_adha'] || ($hm === 11 && $hd === 10);
        }
        return $f;
    }
}
