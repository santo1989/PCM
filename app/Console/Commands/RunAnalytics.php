<?php

namespace App\Console\Commands;

use App\Services\AI\ReportExplainer;
use App\Services\Analytics\CashFlow;
use App\Services\Analytics\Engine;
use App\Services\Analytics\LedgerSeries;
use App\Services\Analytics\ReportMetrics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Builds clean series (LedgerSeries), runs the PHP engine (Engine, CashFlow) on them and stores
 * forecasts, backtest metrics, decompositions, anomalies, recurring payments and simulations
 * under a new run_id. Reads the ledgers, writes only to the analytics_* tables; older runs are kept.
 */
class RunAnalytics extends Command
{
    protected $signature = 'analytics:run {--no-ai : Use template explanations without calling the AI provider}';

    protected $description = 'Recompute forecasts, backtests and anomalies into the analytics cache tables';

    private const HORIZON = 3;

    public function handle()
    {
        // Completed months only: a partial current month would look like a drop in spending.
        $to = now()->subMonthNoOverflow()->endOfMonth()->toDateString();
        $monthly = LedgerSeries::monthly(null, $to);
        $months = array_keys($monthly);
        $future = [];
        for ($i = 1, $c = now()->startOfMonth(); $i <= self::HORIZON; $i++, $c->addMonth()) {
            $future[] = $c->format('Y-m');
        }
        $flags = fn($f) => [$f['ramadan_days'] / 30, (int) $f['eid_fitr'], (int) $f['eid_adha']];

        $series = [];
        foreach (['income', 'expense', 'core'] as $k) {
            $series[$k] = array_values(array_column($monthly, $k));
        }
        foreach (LedgerSeries::categoryMonthly(null, $to)['categories'] as $id => $c) {
            if ($c['type'] === 'EXPENSE') {
                // Models train on core spend; one-offs are reported separately.
                $series["category:$id"] = array_values(array_map(fn($a, $o) => round($a - $o, 2), $c['months'], $c['one_off']));
            }
        }

        $payload = [
            'months' => $months,
            'future' => $future,
            'X' => array_merge(array_map($flags, array_values($monthly)), array_map(fn($ym) => $flags(LedgerSeries::monthFlags($ym)), $future)),
            'series' => $series,
            'changepoint_series' => ['income', 'expense', 'core'],
            'transactions' => DB::table('expense_calculations')->where('types', 'EXPENSE')->where('date', '<=', LedgerSeries::cutoff())
                ->selectRaw('id, category_id cat, date, ' . LedgerSeries::AMOUNT . ' amount')->get()
                ->map(fn($r) => ['id' => $r->id, 'cat' => $r->cat, 'date' => $r->date, 'amount' => (float) $r->amount])->all(),
        ] + $this->cashPayload($monthly);

        $out = Engine::run($payload);
        $out['recurring'] = CashFlow::recurring($payload['recurring_rows'], $payload['today']);
        $out['hand_cash'] = CashFlow::handCash($payload['cash']['burn'], $payload['cash']['balance'], $payload['cash']['withdrawals']);
        $m = $payload['money'];
        $out['savings'] = CashFlow::savings($m['net'], $m['spend'], $m['liquid']);
        $out['events'] = CashFlow::events($months, $m['event_spend'], $m['event_flags'], $m['ahead']);

        $runId = now()->format('YmdHis');
        $stamp = ['run_id' => $runId, 'created_at' => now(), 'updated_at' => now()];
        $rows = ['analytics_model_runs' => [], 'analytics_forecasts' => [], 'analytics_decompositions' => [], 'analytics_anomalies' => [], 'analytics_recurring' => [], 'analytics_simulations' => []];
        foreach ($out['series'] as $s) {
            $sm = array_slice($months, $s['offset']);
            if (!$s['models']) {
                $rows['analytics_model_runs'][] = $stamp + ['series' => $s['name'], 'model' => isset($s['average']) ? 'Average' : 'None', 'n_points' => $s['n'], 'chosen' => true, 'note' => $s['note']];
            }
            foreach ($s['models'] as $m) {
                $rows['analytics_model_runs'][] = $stamp + ['series' => $s['name'], 'model' => $m['model'], 'n_points' => $s['n'], 'chosen' => $m['chosen'],
                    'mase' => $this->num($m['mase']), 'smape' => $m['smape'], 'rmse' => $m['rmse'], 'cover80' => $m['cover80'], 'cover95' => $m['cover95'], 'note' => $m['chosen'] ? $s['note'] : null];
            }
            foreach ($s['forecast'] as $f) {
                $rows['analytics_forecasts'][] = $stamp + ['series' => $s['name'], 'month' => $future[$f['h'] - 1], 'model' => $s['chosen']]
                    + array_map(fn($v) => round($v, 2), array_intersect_key($f, array_flip(['mean', 'lo80', 'hi80', 'lo95', 'hi95'])));
            }
            foreach ($s['decomposition']['trend'] ?? [] as $i => $trend) {
                $rows['analytics_decompositions'][] = $stamp + ['series' => $s['name'], 'month' => $sm[$i], 'observed' => $series[$s['name']][$s['offset'] + $i],
                    'trend' => round($trend, 2), 'seasonal' => round($s['decomposition']['seasonal'][$i], 2), 'remainder' => round($s['decomposition']['remainder'][$i], 2)];
            }
            foreach ($s['anomalies'] as $a) {
                $rows['analytics_anomalies'][] = $stamp + ['kind' => 'month', 'series' => $s['name'], 'month' => $sm[$a['idx']], 'value' => round($a['value'], 2),
                    'expected' => round($a['expected'], 2), 'score' => round($a['z'], 4), 'reason' => sprintf('%.1f robust SDs from the usual level (previous 12 months or same month last year)', $a['z'])];
            }
            foreach ($s['changepoints'] as $c) {
                $rows['analytics_anomalies'][] = $stamp + ['kind' => 'changepoint', 'series' => $s['name'], 'month' => $sm[$c['idx']], 'value' => round($c['after'], 2),
                    'expected' => round($c['before'], 2), 'reason' => sprintf('monthly level moved from %s to %s', number_format($c['before']), number_format($c['after']))];
            }
        }
        foreach ($out['recurring'] as $r) {
            $rows['analytics_recurring'][] = $stamp + ['source' => $r['src'], 'group_key' => (string) $r['group'], 'name' => $r['name'], 'cadence' => $r['cadence'],
                'occurrences' => $r['count'], 'median_gap_days' => $r['median_gap_days'], 'amount' => round($r['amount'], 2), 'monthly_equivalent' => round($r['monthly_equivalent'], 2),
                'last_date' => $r['last_date'], 'next_date' => $r['next_date'], 'calendar' => json_encode($r['calendar'])];
        }
        foreach (['hand_cash', 'savings', 'events'] as $kind) {
            $rows['analytics_simulations'][] = $stamp + ['kind' => $kind, 'result' => json_encode($out[$kind])];
        }
        foreach ($out['transactions'] as $t) {
            $rows['analytics_anomalies'][] = $stamp + ['kind' => 'transaction', 'expense_calculation_id' => $t['id'], 'score' => round($t['score'], 4), 'reason' => $t['reason']];
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $table => $list) {
                // Same column set within a table so batch inserts line up.
                $cols = array_fill_keys(array_unique(array_merge(...array_map('array_keys', $list ?: [[]]))), null);
                foreach (array_chunk($list, 200) as $chunk) {
                    DB::table($table)->insert(array_map(fn($r) => array_merge($cols, $r), $chunk));
                }
            }
        });

        $this->table(['Series', 'Model', 'MASE', 'sMAPE', '80% cover', '95% cover', 'Months', 'Note'], array_map(fn($r) => [
            $r['series'], $r['model'], $r['mase'] ?? '-', isset($r['smape']) ? round($r['smape'], 1) : '-',
            isset($r['cover80']) ? round($r['cover80'] * 100) . '%' : '-', isset($r['cover95']) ? round($r['cover95'] * 100) . '%' : '-', $r['n_points'], $r['note'],
        ], array_values(array_filter($rows['analytics_model_runs'], fn($r) => $r['chosen']))));
        $this->info(sprintf('Run %s: %d forecasts, %d anomalies/change-points stored.', $runId, count($rows['analytics_forecasts']), count($rows['analytics_anomalies'])));

        // Explanations come after the results are saved: a slow or failing AI never blocks the numbers.
        $labels = ['income' => 'Income', 'expense' => 'Total spending', 'core' => 'Everyday spending']
            + DB::table('categories')->pluck('name', 'id')->mapWithKeys(fn($n, $id) => ["category:$id" => $n])->all();
        foreach (ReportMetrics::build($out, $monthly, $future, $labels) as $report => $r) {
            // Templates obey the same rule as the AI: every number must be in the metrics.
            if ($bad = ReportExplainer::unknownNumbers(implode(' ', array_merge($r['template']['insights'], $r['template']['actions'])), $r['metrics'])) {
                $this->warn("Template for $report uses numbers not in its metrics: " . implode(', ', $bad));
            }
            $e = $this->option('no-ai') ? $r['template'] + ['source' => 'template', 'model' => null, 'rejected' => null]
                : ReportExplainer::explain($report, $r['metrics'], $r['template']);
            DB::table('analytics_insights')->insert($stamp + ['report' => $report, 'source' => $e['source'], 'model' => $e['model'],
                'metrics' => json_encode($r['metrics']), 'insights' => json_encode($e['insights']), 'actions' => json_encode($e['actions']), 'rejected' => $e['rejected']]);
            $this->line(sprintf('Insights %-10s %s%s', $report, $e['source'], $e['rejected'] ? " (AI answer rejected: {$e['rejected']})" : ''));
        }
        return self::SUCCESS;
    }

    /** Phase 3 inputs: recurring candidates, daily hand-cash spend, monthly money, Eid calendar. */
    private function cashPayload(array $monthly): array
    {
        $today = LedgerSeries::cutoff();
        $yearAgo = now()->subYear()->toDateString();
        $accounts = LedgerSeries::accounts(null, $today);
        $cash = LedgerSeries::CASH_ACCOUNT;

        // Cash spent from hand per day (transfers out of hand cash are moves, not spending).
        $burn = DB::table('hand_cashes')->where('rules', $cash)->where('types', 'WIDROWS')->whereNull('transfer_pair_id')
            ->whereBetween('date', [$yearAgo, $today])->selectRaw('date, SUM(' . LedgerSeries::AMOUNT . ') amt')->groupBy('date')->pluck('amt', 'date');
        $days = [];
        for ($d = now()->subYear(); $d->toDateString() <= $today; $d->addDay()) {
            $days[] = (float) ($burn[$d->toDateString()] ?? 0);
        }
        $withdrawals = DB::table('hand_cashes')->where('rules', $cash)->where('types', 'SAVE')->whereBetween('date', [$yearAgo, $today])
            ->selectRaw('date, SUM(' . LedgerSeries::AMOUNT . ') amt')->groupBy('date')->orderBy('date')->get()->map(fn($r) => [$r->date, (float) $r->amt])->all();

        $recurring = DB::table('expense_calculations')->where('types', 'EXPENSE')->where('date', '<=', $today)
            ->selectRaw("'ledger' src, category_id grp, name, date, " . LedgerSeries::AMOUNT . ' amount')->get()
            // Account rows: real payments, plus transfers INTO savings/debt accounts (DPS deposits, loan and card payments).
            ->concat(DB::table('hand_cashes')->where('rules', '<>', $cash)->whereNull('expense_calculation_id')->where('date', '<=', $today)
                ->where(fn($q) => $q->whereNull('transfer_pair_id')->orWhere(fn($q) => $q->where('types', 'SAVE')->whereNotIn('rules', LedgerSeries::LIQUID_ACCOUNTS)))
                ->selectRaw("'account' src, CONCAT(types, '|', rules) grp, name, date, " . LedgerSeries::AMOUNT . ' amount')->get())
            ->map(fn($r) => ['src' => $r->src, 'group' => $r->grp, 'name' => $r->name, 'date' => $r->date, 'amount' => (float) $r->amount])->all();

        // Real spending (one-offs included); SAVINGS-rule rows are money kept, not spent.
        $spend = array_map(fn($m) => $m['needs'] + $m['wants'] + $m['other'], array_values($monthly));
        $ahead = [];
        for ($i = 0, $c = now()->startOfMonth(); $i < 13; $i++, $c->addMonth()) {
            $f = LedgerSeries::monthFlags($c->format('Y-m'));
            $ahead[] = [$c->format('Y-m'), [$f['ramadan_days'], (int) $f['eid_fitr'], (int) $f['eid_adha']]];
        }

        return [
            'today' => $today,
            'recurring_rows' => $recurring,
            'cash' => ['burn' => $days, 'balance' => $accounts[$cash]['closing'] ?? 0, 'withdrawals' => $withdrawals],
            'money' => [
                'net' => array_map(fn($m, $s) => $m['income'] - $s, array_values($monthly), $spend),
                'spend' => $spend,
                'liquid' => array_sum(array_map(fn($a) => $accounts[$a]['closing'] ?? 0, LedgerSeries::LIQUID_ACCOUNTS)),
                'event_spend' => array_map(fn($m) => $m['needs'] + $m['wants'], array_values($monthly)),
                'event_flags' => array_map(fn($m) => [$m['ramadan_days'], (int) $m['eid_fitr'], (int) $m['eid_adha']], array_values($monthly)),
                'ahead' => $ahead,
            ],
        ];
    }

    private function num($v): ?float
    {
        return is_numeric($v) && is_finite($v) ? round($v, 4) : null;
    }
}
