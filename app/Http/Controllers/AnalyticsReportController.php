<?php

namespace App\Http\Controllers;

use App\Services\Analytics\Inflation;
use App\Services\Analytics\LedgerSeries;
use App\Services\Analytics\PeriodContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Analytics page: reads the latest `php artisan analytics:run` results (forecasts with
 * back-tested accuracy, anomalies, recurring payments, simulations, explanations) next to
 * actuals from LedgerSeries, which reconcile exactly with the ledger.
 */
class AnalyticsReportController extends Controller
{
    public function index()
    {
        return view('backend.reports.analytics', $this->pageData());
    }

    /** The same data as the page, as an Excel workbook (one sheet per section). */
    public function export()
    {
        $data = $this->pageData();
        abort_unless($data['run'], 404, 'No analytics yet: run php artisan analytics:run first.');
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\AnalyticsExport($data), "analytics-{$data['run']}.xlsx");
    }

    private function pageData(): array
    {
        $run = DB::table('analytics_model_runs')->max('run_id');
        if (!$run) {
            return ['run' => null];
        }
        $names = DB::table('categories')->pluck('name', 'id');
        $label = fn($s) => ['income' => 'Income', 'expense' => 'Total spending', 'core' => 'Everyday spending'][$s]
            ?? ($names[substr($s, 9)] ?? $s);

        $to = now()->subMonthNoOverflow()->endOfMonth()->toDateString();
        $monthly = LedgerSeries::monthly(null, $to);
        $actuals = $withOneOffs = [];
        foreach (['income', 'expense', 'core'] as $k) {
            $actuals[$k] = array_values(array_column($monthly, $k));
        }
        $withOneOffs['core'] = array_values(array_map(fn($m) => $m['core'] + $m['one_off'], $monthly));
        foreach (LedgerSeries::categoryMonthly(null, $to)['categories'] as $id => $c) {
            if ($c['type'] === 'EXPENSE') {
                $actuals["category:$id"] = array_values(array_map(fn($a, $o) => round($a - $o, 2), $c['months'], $c['one_off']));
                $withOneOffs["category:$id"] = array_values($c['months']);
            }
        }

        $models = DB::table('analytics_model_runs')->where('run_id', $run)->get();
        $chosen = $models->where('chosen', true)->keyBy('series');
        $forecasts = DB::table('analytics_forecasts')->where('run_id', $run)->orderBy('month')->get()->groupBy('series');
        $anomalies = DB::table('analytics_anomalies')->where('run_id', $run)->get();
        $series = [];
        foreach ($chosen as $name => $m) {
            if (!isset($forecasts[$name]) && !in_array($name, ['income', 'expense', 'core'], true)) {
                continue;
            }
            $series[$name] = [
                'label' => $label($name),
                'model' => $m->model, 'mase' => $m->mase !== null ? (float) $m->mase : null, 'note' => $m->note,
                'months' => array_keys($monthly),
                'actual' => $actuals[$name] ?? [],
                'with_one_offs' => $withOneOffs[$name] ?? $actuals[$name] ?? [],
                'forecast' => ($forecasts[$name] ?? collect())->map(fn($f) => ['month' => $f->month, 'mean' => (float) $f->mean,
                    'lo80' => (float) $f->lo80, 'hi80' => (float) $f->hi80, 'lo95' => (float) $f->lo95, 'hi95' => (float) $f->hi95])->values(),
                'anomalies' => $anomalies->where('series', $name)->where('kind', 'month')->map(fn($a) => ['month' => $a->month, 'reason' => $a->reason])->values(),
                'changepoints' => $anomalies->where('series', $name)->where('kind', 'changepoint')->map(fn($a) => ['month' => $a->month, 'reason' => $a->reason])->values(),
            ];
        }

        $sims = DB::table('analytics_simulations')->where('run_id', $run)->pluck('result', 'kind')->map(fn($j) => json_decode($j, true));
        $insights = DB::table('analytics_insights')->where('run_id', $run)->get()->keyBy('report')
            ->map(fn($i) => ['source' => $i->source, 'model' => $i->model, 'rejected' => $i->rejected,
                'insights' => json_decode($i->insights, true), 'actions' => json_decode($i->actions, true), 'metrics' => json_decode($i->metrics, true)]);
        $recurring = DB::table('analytics_recurring')->where('run_id', $run)->orderBy('next_date')->get()
            ->map(fn($r) => (array) $r + ['label' => $r->source === 'ledger' ? ($names[$r->group_key] ?? 'Expense') : str_replace('_', ' ', explode('|', $r->group_key)[1]) . ' deposit']);
        // 12-month committed-cost calendar: expected payments per month.
        $calendar = [];
        foreach ($recurring as $r) {
            foreach (json_decode($r['calendar'], true) as $d) {
                $calendar[substr($d, 0, 7)] = ($calendar[substr($d, 0, 7)] ?? 0) + (float) $r['amount'];
            }
        }
        ksort($calendar);

        $transactions = DB::table('analytics_anomalies as a')->join('expense_calculations as e', 'e.id', '=', 'a.expense_calculation_id')
            ->join('categories as c', 'c.id', '=', 'e.category_id')->where('a.run_id', $run)->where('a.kind', 'transaction')
            ->orderByDesc('e.date')->get(['e.id', 'e.date', 'e.name', 'e.amount', 'c.name as category', 'a.reason']);

        $backtest = $models->sortBy(fn($m) => [$m->series === 'income' ? 0 : ($m->series === 'expense' ? 1 : ($m->series === 'core' ? 2 : 3)), $m->series, $m->mase ?? INF])
            ->map(fn($m) => (array) $m + ['label' => $label($m->series)])->groupBy('series');

        $deflate = [];
        foreach (array_keys($monthly) as $ym) {
            $deflate[$ym] = round(Inflation::factor($ym), 4);
        }

        return [
            'run' => $run,
            'deflate' => $deflate,
            'accounts' => $this->accounts(),
            'runAt' => DB::table('analytics_model_runs')->where('run_id', $run)->value('created_at'),
            'series' => $series,
            'sims' => $sims,
            'insights' => $insights,
            'recurring' => $recurring,
            'calendar' => $calendar,
            'transactions' => $transactions,
            'backtest' => $backtest,
            'quality' => [
                'months' => count($monthly),
                'from' => array_key_first($monthly),
                'to' => array_key_last($monthly),
                'one_offs' => count(LedgerSeries::oneOffIds()),
                'overrides' => DB::table('analytics_one_off_overrides')->count(),
                'future_rows' => DB::table('expense_calculations')->where('date', '>', LedgerSeries::cutoff())->count(),
            ],
        ];
    }

    /**
     * Bank / account details from the hand-cash ledger: balance, last 12 months of money in and out
     * (real flows vs transfers between own accounts), last activity, idle flag, monthly balances.
     */
    private function accounts(): array
    {
        $today = LedgerSeries::cutoff();
        $from = now()->subMonthsNoOverflow(23)->startOfMonth()->toDateString();
        $last = DB::table('hand_cashes')->where('date', '<=', $today)->groupBy('rules')->selectRaw('rules, MAX(date) d')->pluck('d', 'rules');
        $debt = ['LOAN', 'DPSLOAN', 'CREDITCARD', 'MYLOAN'];
        $out = [];
        foreach (LedgerSeries::accounts($from, $today) as $name => $a) {
            $year = array_slice($a['months'], -12, 12, true);
            $sum = fn($k) => round(array_sum(array_column($year, $k)), 2);
            $role = in_array($name, LedgerSeries::LIQUID_ACCOUNTS, true) ? 'everyday' : (in_array($name, $debt, true) ? 'debt' : 'savings');
            $quietDays = isset($last[$name]) ? Carbon::parse($last[$name])->diffInDays(now()) : null;
            $out[$name] = [
                'name' => str_replace('_', ' ', $name), 'role' => $role, 'balance' => $a['closing'],
                'in' => $sum('in'), 'out' => $sum('out'), 'transfer_in' => $sum('transfer_in'), 'transfer_out' => $sum('transfer_out'),
                'net' => round($sum('in') + $sum('transfer_in') - $sum('out') - $sum('transfer_out'), 2),
                'last_activity' => $last[$name] ?? null,
                // Money sitting unused in an everyday account (1,000+ with no entries for 90+ days); DPS/FD are meant to sit.
                'idle' => $role === 'everyday' && $a['closing'] >= 1000 && $quietDays !== null && $quietDays >= 90,
                'months' => array_keys($a['months']),
                'balances' => array_column($a['months'], 'closing'),
            ];
        }
        uasort($out, fn($x, $y) => [$x['role'] !== 'everyday', -abs($x['balance'])] <=> [$y['role'] !== 'everyday', -abs($y['balance'])]);
        return $out;
    }

    /** The analytics block alone (HTML), for pages that change their date range without reloading. */
    public function context(Request $request)
    {
        $data = $request->validate(['start_date' => 'required|date', 'end_date' => 'required|date']);
        return view('components.analytics-context', ['ctx' => PeriodContext::build($data['start_date'], $data['end_date'])]);
    }

    /** Re-runs the analytics now (normally nightly at 01:30). Writes only to the analytics_* tables. */
    public function recompute()
    {
        set_time_limit(300); // the run itself takes ~15s; AI calls can add up to a minute when slow
        $code = Artisan::call('analytics:run');
        return redirect()->route('analytics.index')->with($code === 0 ? 'success' : 'error', $code === 0 ? 'Analytics recomputed.' : 'Recompute failed; see the log.');
    }
}
