<?php

namespace App\Services\Analytics;

/**
 * Compact, anonymised metrics per report (aggregates and category labels only: no notes,
 * people's names or account numbers) plus rule-based template text from the same numbers.
 * The metrics are what the AI may see; the template is the fallback explanation.
 */
final class ReportMetrics
{
    /**
     * @param array $out      engine output (Engine::run + CashFlow results)
     * @param array $monthly  LedgerSeries::monthly() of completed months
     * @param array $future   forecast months (Y-m)
     * @param array $labels   series name => readable label ('category:12' => 'SHOPPING')
     * @return array report => ['metrics' => [...], 'template' => ['insights' => [...], 'actions' => [...]]]
     */
    public static function build(array $out, array $monthly, array $future, array $labels): array
    {
        $series = array_column($out['series'], null, 'name');
        $label = fn($s) => $labels[$s] ?? $s;
        $r = fn($v) => round((float) $v);
        $forecast = function ($name) use ($series, $future, $r) {
            $s = $series[$name] ?? null;
            if (!$s || !$s['forecast']) {
                return null;
            }
            $chosen = array_column($s['models'], null, 'model')[$s['chosen']] ?? [];
            return ['month' => $future[0], 'expected' => $r($s['forecast'][0]['mean']), 'range_80' => [$r($s['forecast'][0]['lo80']), $r($s['forecast'][0]['hi80'])],
                'model' => $s['chosen'], 'backtest_mase' => round($chosen['mase'] ?? 0, 2)];
        };

        // ---- executive summary: last completed month vs 50/30/20 ----
        $ym = array_key_last($monthly);
        $m = $monthly[$ym];
        $pct = fn($v) => $m['income'] > 0 ? round(100 * $v / $m['income']) : null;
        $spent = $m['needs'] + $m['wants'] + $m['other'];
        $exec = [
            'month' => $ym,
            'income' => $r($m['income']),
            'spending' => $r($spent),
            'kept' => $r($m['income'] - $spent),
            'savings_rate_percent' => $pct($m['income'] - $spent),
            'needs_percent_of_income' => $pct($m['needs']),
            'wants_percent_of_income' => $pct($m['wants']),
            'benchmark_50_30_20' => ['needs' => 50, 'wants' => 30, 'savings' => 20],
            'one_off_spending' => $r($m['one_off']),
            'next_month_income' => $forecast('income'),
            'next_month_core_spending' => $forecast('core'),
            'liquid_money' => $r($out['savings']['liquid']),
            'emergency_months_covered' => $out['savings']['emergency_months']['p50'],
            'hand_cash' => $r($out['hand_cash']['balance']),
        ];
        $t = [];
        $t[] = $exec['kept'] >= 0
            ? sprintf('In %s you earned %s and spent %s, keeping %s (%s%% of income).', $ym, number_format($exec['income']), number_format($exec['spending']), number_format($exec['kept']), $exec['savings_rate_percent'] ?? 0)
            : sprintf('In %s you spent %s against income of %s, so you spent %s more than you earned.', $ym, number_format($exec['spending']), number_format($exec['income']), number_format(-$exec['kept']));
        if ($exec['one_off_spending'] > 0) {
            $t[] = sprintf('One-off purchases made up %s of that spending.', number_format($exec['one_off_spending']));
        }
        $t[] = sprintf('Needs took %s%% and wants %s%% of income, against a 50/30/20 guide.', $exec['needs_percent_of_income'] ?? 0, $exec['wants_percent_of_income'] ?? 0);
        if ($exec['next_month_core_spending']) {
            $f = $exec['next_month_core_spending'];
            $t[] = sprintf('Everyday spending for %s is expected around %s (likely %s to %s; model %s, MASE %s).', $f['month'], number_format($f['expected']), number_format($f['range_80'][0]), number_format($f['range_80'][1]), $f['model'], $f['backtest_mase']);
        }
        $t[] = sprintf('Your liquid money of %s would cover about %s months of spending if income stopped.', number_format($exec['liquid_money']), $exec['emergency_months_covered']);
        $a = [($exec['savings_rate_percent'] ?? 0) < 20 ? 'Aim to keep at least 20% of income; review the largest wants first.' : 'You are above the 20% savings guide; consider moving the surplus into a DPS or FD.'];
        $reports['executive'] = ['metrics' => $exec, 'template' => ['insights' => $t, 'actions' => $a]];

        // ---- hand cash ----
        $h = $out['hand_cash'];
        $cash = ['balance' => $r($h['balance']), 'average_daily_spend_90d' => $r($h['avg_daily_burn_90d']),
            'days_until_empty' => $h['days_until_empty'], 'chance_empty_within_7_days_percent' => round(100 * $h['prob_empty_within'][7]),
            'suggested_withdrawal_for_7_days' => $r($h['suggested_withdrawal']['7']), 'suggested_withdrawal_for_30_days' => $r($h['suggested_withdrawal']['30']),
            'withdraw_again_below' => $r($h['reorder_point']), 'usual_withdrawal' => isset($h['history']) ? $r($h['history']['median_withdrawal']) : null,
            'usual_days_between_withdrawals' => $h['history']['median_days_between'] ?? null];
        $reports['hand_cash'] = ['metrics' => $cash, 'template' => [
            'insights' => [
                sprintf('You have %s in hand and spend about %s a day from it.', number_format($cash['balance']), number_format($cash['average_daily_spend_90d'])),
                sprintf('At that pace it lasts about %d days (between %d and %d in most simulations).', $cash['days_until_empty']['p50'], $cash['days_until_empty']['p10'], $cash['days_until_empty']['p90']),
                sprintf('There is a %d%% chance it runs out within 7 days.', $cash['chance_empty_within_7_days_percent']),
            ],
            'actions' => [sprintf('Withdraw %s to cover the next 7 days, or %s for 30 days.', number_format($cash['suggested_withdrawal_for_7_days']), number_format($cash['suggested_withdrawal_for_30_days']))],
        ]];

        // ---- recurring commitments (category or account type only; account numbers dropped) ----
        $items = array_map(fn($x) => ['what' => $x['src'] === 'ledger' ? ($labels['category:' . $x['group']] ?? 'expense') : str_replace('_', ' ', explode('|', $x['group'])[1]) . ' deposit',
            'cadence' => $x['cadence'], 'amount' => $r($x['amount']), 'next_date' => $x['next_date']], $out['recurring']);
        $total = $r(array_sum(array_column($out['recurring'], 'monthly_equivalent')));
        // Totals per payment type, so a sum like "two Islamic DPS deposits = 15,000" is in the data, not invented.
        $byType = [];
        foreach ($items as $i) {
            $byType[$i['what']] = ($byType[$i['what']] ?? 0) + $i['amount'];
        }
        $reports['recurring'] = ['metrics' => ['payments' => count($items), 'committed_per_month' => $total, 'total_per_payment_type' => $byType, 'items' => $items], 'template' => [
            'insights' => array_merge(
                [sprintf('%d regular payments commit about %s a month.', count($items), number_format($total))],
                array_map(fn($i) => sprintf('%s: %s %s, next due %s.', $i['what'], number_format($i['amount']), $i['cadence'], $i['next_date']), array_slice($items, 0, 4))
            ),
            'actions' => ['Keep the committed amount in the bank before each due date.'],
        ]];

        // ---- Eid / Ramadan sinking funds ----
        $ev = array_map(fn($e) => ['event' => $e['event'], 'usual_extra_spend' => $e['median_extra'] !== null ? $r($e['median_extra']) : null,
            'next_month' => $e['next_month'], 'months_left' => $e['months_left'], 'save_per_month' => $e['monthly_saving'] !== null ? $r($e['monthly_saving']) : null], $out['events']);
        $saveTotal = $r(array_sum(array_column($ev, 'save_per_month')));
        $reports['events'] = ['metrics' => ['events' => $ev, 'save_per_month_for_all' => $saveTotal], 'template' => [
            'insights' => array_map(fn($e) => sprintf('%s usually costs %s extra; next in %s.', $e['event'], number_format($e['usual_extra_spend'] ?? 0), $e['next_month'] ?? 'over a year'), $ev),
            'actions' => [sprintf('Set aside %s a month from now for the coming events.', number_format($saveTotal))],
        ]];

        // ---- unusual months and level shifts in the last 12 months ----
        $since = array_keys($monthly)[max(0, count($monthly) - 12)];
        $months = array_keys($monthly);
        $unusual = $shifts = [];
        foreach ($out['series'] as $s) {
            foreach ($s['anomalies'] as $x) {
                $mo = $months[$s['offset'] + $x['idx']];
                if ($mo >= $since) {
                    $unusual[] = ['what' => $label($s['name']), 'month' => $mo, 'actual' => $r($x['value']), 'usual' => $r($x['expected'])];
                }
            }
            foreach ($s['changepoints'] as $c) {
                $shifts[] = ['what' => $label($s['name']), 'from_month' => $months[$s['offset'] + $c['idx']], 'level_before' => $r($c['before']), 'level_after' => $r($c['after'])];
            }
        }
        usort($unusual, fn($a, $b) => abs($b['actual'] - $b['usual']) <=> abs($a['actual'] - $a['usual']));
        $unusual = array_slice($unusual, 0, 5);
        $reports['anomalies'] = ['metrics' => ['unusual_months' => $unusual, 'level_shifts' => $shifts, 'unusual_transactions' => count($out['transactions'])], 'template' => [
            'insights' => array_slice(array_merge(
                array_map(fn($u) => sprintf('%s in %s was %s against a usual %s.', $u['what'], $u['month'], number_format($u['actual']), number_format($u['usual'])), array_slice($unusual, 0, 3)),
                array_map(fn($c) => sprintf('%s moved from about %s to %s a month starting %s.', $c['what'], number_format($c['level_before']), number_format($c['level_after']), $c['from_month']), $shifts),
                [sprintf('%d individual transactions look unusual for their category.', count($out['transactions']))]
            ), 0, 5),
            'actions' => ['Check the unusual transactions and mark genuine one-offs so forecasts ignore them.'],
        ]];

        return $reports;
    }
}
