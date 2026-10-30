<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Analytics page as a workbook: one sheet per section, from the same data the page shows. */
class AnalyticsExport implements WithMultipleSheets
{
    public function __construct(private array $d)
    {
    }

    public function sheets(): array
    {
        $d = $this->d;
        $exec = $d['insights']['executive']['metrics'] ?? [];
        $h = $d['sims']['hand_cash'] ?? [];
        $s = $d['sims']['savings'] ?? [];

        $summary = [['Analytics run', $d['run']], ['Computed at', (string) $d['runAt']], ['Month', $exec['month'] ?? '']];
        foreach (['income' => 'Income', 'spending' => 'Spending', 'kept' => 'Kept', 'savings_rate_percent' => 'Savings rate %',
            'needs_percent_of_income' => 'Needs % of income', 'wants_percent_of_income' => 'Wants % of income', 'one_off_spending' => 'One-off spending',
            'liquid_money' => 'Liquid money', 'emergency_months_covered' => 'Emergency months covered'] as $k => $label) {
            $summary[] = [$label, $exec[$k] ?? ''];
        }
        if ($h) {
            $summary[] = ['Hand cash now', $h['balance']];
            $summary[] = ['Average daily cash spend (90 days)', round($h['avg_daily_burn_90d'])];
            $summary[] = ['Days until hand cash runs out (p10 / p50 / p90)', implode(' / ', $h['days_until_empty'])];
            $summary[] = ['Suggested withdrawal: 7 days / 30 days', $h['suggested_withdrawal']['7'] . ' / ' . $h['suggested_withdrawal']['30']];
            $summary[] = ['Withdraw again below', $h['reorder_point']];
        }
        if ($s) {
            $summary[] = ['Savings over 12 months (p10 / p50 / p90)', implode(' / ', array_map('round', [$s['cumulative_net'][11][1], $s['cumulative_net'][11][9], $s['cumulative_net'][11][17]]))];
        }
        $summary[] = ['Data', "{$d['quality']['months']} completed months ({$d['quality']['from']} to {$d['quality']['to']}); {$d['quality']['one_offs']} one-offs kept out of forecasts"];

        $forecasts = [];
        foreach ($d['series'] as $x) {
            foreach ($x['forecast'] as $f) {
                $forecasts[] = [$x['label'], $f['month'], $x['model'], $x['mase'], round($f['mean']), round($f['lo80']), round($f['hi80']), round($f['lo95']), round($f['hi95'])];
            }
        }
        $accuracy = [];
        foreach ($d['backtest'] as $rows) {
            foreach ($rows as $r) {
                $accuracy[] = [$r['label'], $r['model'], $r['chosen'] ? 'yes' : '', $r['mase'], $r['smape'], $r['rmse'], $r['cover80'], $r['cover95'], $r['n_points'], $r['note']];
            }
        }
        $unusualMonths = [];
        foreach ($d['series'] as $x) {
            foreach (array_merge($x['anomalies']->all(), $x['changepoints']->all()) as $a) {
                $unusualMonths[] = [$x['label'], $a['month'], $a['reason']];
            }
        }
        $insights = [];
        foreach ($d['insights'] as $report => $i) {
            foreach ($i['insights'] as $t) {
                $insights[] = [$report, 'insight', $t, $i['source']];
            }
            foreach ($i['actions'] as $t) {
                $insights[] = [$report, 'action', $t, $i['source']];
            }
        }

        return [
            self::sheet('Summary', ['Item', 'Value'], $summary),
            self::sheet('Forecasts', ['Series', 'Month', 'Model', 'Back-test MASE', 'Expected', '80% low', '80% high', '95% low', '95% high'], $forecasts),
            self::sheet('Accuracy', ['Series', 'Model', 'Chosen', 'MASE', 'sMAPE %', 'RMSE', '80% range hit', '95% range hit', 'Months', 'Note'], $accuracy),
            self::sheet('Accounts', ['Account', 'Role', 'Balance', 'In (12 mo)', 'of which transfers in', 'Out (12 mo)', 'of which transfers out', 'Net (12 mo)', 'Last entry', 'Idle'],
                collect($d['accounts'])->map(fn($a) => [$a['name'], $a['role'], $a['balance'], $a['in'] + $a['transfer_in'], $a['transfer_in'], $a['out'] + $a['transfer_out'], $a['transfer_out'], $a['net'], $a['last_activity'], $a['idle'] ? 'yes' : ''])->values()->all()),
            self::sheet('Regular payments', ['Payment', 'Name', 'Every', 'Seen', 'Amount', 'Per month', 'Last paid', 'Next due'],
                collect($d['recurring'])->map(fn($r) => [$r['label'], $r['name'], $r['cadence'], $r['occurrences'], (float) $r['amount'], (float) $r['monthly_equivalent'], $r['last_date'], $r['next_date']])->all()),
            self::sheet('Commitment calendar', ['Month', 'Committed'], collect($d['calendar'])->map(fn($v, $m) => [$m, $v])->values()->all()),
            self::sheet('Ramadan & Eid', ['Event', 'Usual extra spend', 'Next', 'Months left', 'Save per month'],
                collect($d['sims']['events'] ?? [])->map(fn($e) => [$e['event'], $e['median_extra'] !== null ? round($e['median_extra']) : null, $e['next_month'], $e['months_left'], $e['monthly_saving'] !== null ? round($e['monthly_saving']) : null])->all()),
            self::sheet('Unusual months', ['Series', 'Month', 'Reason'], $unusualMonths),
            self::sheet('Unusual transactions', ['Date', 'Item', 'Category', 'Amount', 'Why flagged'],
                collect($d['transactions'])->map(fn($t) => [$t->date, $t->name, $t->category, (float) str_replace(',', '', $t->amount), $t->reason])->all()),
            self::sheet('Insights', ['Report', 'Kind', 'Text', 'Written by'], $insights),
        ];
    }

    private static function sheet(string $title, array $head, array $rows): object
    {
        return new class($title, $head, $rows) implements FromArray, WithTitle, ShouldAutoSize {
            public function __construct(private string $title, private array $head, private array $rows)
            {
            }

            public function array(): array
            {
                return array_merge([$this->head], $this->rows);
            }

            public function title(): string
            {
                return $this->title;
            }
        };
    }
}
