<?php

namespace App\Services\Analytics;

use App\Services\AI\ReportExplainer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Analytics context for any report period: actuals (exactly from the ledger via LedgerSeries),
 * comparisons (previous period, same period last year, same Hijri period last year, 3/6/12-month
 * averages), an unusual-month check, next-month forecast with back-tested accuracy, unusual items
 * from the latest analytics run, a data-quality note and checked plain-language insights.
 */
final class PeriodContext
{
    private const HIJRI_YEAR_DAYS = 354; // shift by one lunar year: Ramadan/Eid land in the same place

    public static function build(string $start, string $end): array
    {
        $today = Carbon::parse(LedgerSeries::cutoff());
        $s = Carbon::parse($start)->startOfDay();
        $e = Carbon::parse($end)->startOfDay()->min($today);
        if ($e->lt($s)) {
            $e = $s->copy();
        }
        $days = $s->diffInDays($e) + 1;
        $wholeMonths = $s->day === 1 && $e->isSameDay($e->copy()->endOfMonth()) ? $s->diffInMonths($e->copy()->addDay()) : null;
        $periodMonths = $wholeMonths ?: $days / 30.44;

        $cur = self::totals($s, $e);
        $refs = [
            'previous_period' => $wholeMonths
                ? [$s->copy()->subMonthsNoOverflow($wholeMonths), $s->copy()->subDay()]
                : [$s->copy()->subDays($days), $s->copy()->subDay()],
            'same_period_last_year' => [$s->copy()->subYearNoOverflow(), $e->copy()->subYearNoOverflow()],
            'same_hijri_period_last_year' => [$s->copy()->subDays(self::HIJRI_YEAR_DAYS), $e->copy()->subDays(self::HIJRI_YEAR_DAYS)],
        ];
        $first = Carbon::parse(LedgerSeries::firstDate());
        $compare = [];
        foreach ($refs as $key => [$a, $b]) {
            if ($b->lt($first)) {
                continue; // before the ledger starts: no comparison rather than a fake zero
            }
            $compare[$key] = ['from' => $a->toDateString(), 'to' => $b->toDateString()] + self::totals($a, $b);
        }
        // Rolling averages of the completed months before the period, scaled to its length.
        foreach ([3, 6, 12] as $k) {
            $from = $s->copy()->startOfMonth()->subMonthsNoOverflow($k)->max($first->copy()->startOfMonth());
            $to = $s->copy()->startOfMonth()->subDay();
            if ($to->lt($from)) {
                continue;
            }
            $m = LedgerSeries::monthly($from->toDateString(), $to->toDateString());
            $n = count($m);
            $compare["average_{$k}_months"] = ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'months' => $n]
                + array_map(fn($key) => round(array_sum(array_column($m, $key)) / $n * $periodMonths, 2), ['income' => 'income', 'expense' => 'expense', 'core' => 'core', 'one_off' => 'one_off']);
        }
        // Real (inflation-adjusted) change: both sides in today's taka, each at its period's middle month.
        $mid = fn($a, $b) => Carbon::parse($a)->addDays(intdiv(Carbon::parse($a)->diffInDays(Carbon::parse($b)), 2))->format('Y-m');
        $fCur = Inflation::factor($mid($s, $e));
        foreach ($compare as $key => $c) {
            $fRef = Inflation::factor($mid($c['from'], $c['to']));
            foreach (['income', 'expense', 'core'] as $f) {
                $compare[$key]["{$f}_change_percent"] = $c[$f] > 0 ? round(100 * ($cur[$f] - $c[$f]) / $c[$f]) : null;
                $compare[$key]["{$f}_real_change_percent"] = $c[$f] > 0 ? round(100 * ($cur[$f] * $fCur - $c[$f] * $fRef) / ($c[$f] * $fRef)) : null;
            }
        }

        // Single calendar month: is spending unusual vs the previous 12 months and the same month last year?
        $unusual = null;
        if ($wholeMonths === 1) {
            $hist = LedgerSeries::monthly($s->copy()->subMonthsNoOverflow(12)->max($first)->toDateString(), $e->toDateString());
            if (count($hist) === 13) {
                $hit = collect(Engine::monthAnomalies(array_values(array_column($hist, 'expense'))))->firstWhere('idx', 12);
                $unusual = $hit ? ['usual' => round($hit['expected']), 'score' => round($hit['z'], 1)] : false;
            }
        }

        $run = DB::table('analytics_model_runs')->max('run_id');
        $forecast = [];
        if ($run) {
            $chosen = DB::table('analytics_model_runs')->where('run_id', $run)->where('chosen', true)->whereIn('series', ['income', 'core'])->get()->keyBy('series');
            foreach (DB::table('analytics_forecasts')->where('run_id', $run)->whereIn('series', ['income', 'core'])->orderBy('month')->get()->unique('series') as $f) {
                $forecast[$f->series] = ['month' => $f->month, 'expected' => round($f->mean), 'range_80' => [round($f->lo80), round($f->hi80)],
                    'model' => $f->model, 'backtest_mase' => isset($chosen[$f->series]) ? round($chosen[$f->series]->mase, 2) : null];
            }
        }
        $anomalies = $run ? DB::table('analytics_anomalies')->where('run_id', $run)->where('kind', 'month')->whereIn('series', ['income', 'expense', 'core'])
            ->whereBetween('month', [$s->format('Y-m'), $e->format('Y-m')])->get(['series', 'month', 'value', 'expected', 'reason'])->map(fn($a) => (array) $a)->all() : [];
        $transactions = $run ? DB::table('analytics_anomalies as a')->join('expense_calculations as x', 'x.id', '=', 'a.expense_calculation_id')
            ->join('categories as c', 'c.id', '=', 'x.category_id')->where('a.run_id', $run)->where('a.kind', 'transaction')
            ->whereBetween('x.date', [$s->toDateString(), $e->toDateString()])->get(['x.date', 'x.name', 'x.amount', 'c.name as category', 'a.reason'])->all() : [];

        $ctx = [
            'from' => $s->toDateString(), 'to' => $e->toDateString(), 'run' => $run,
            'current' => $cur, 'compare' => $compare, 'unusual' => $unusual, 'forecast' => $forecast,
            'anomalies' => $anomalies, 'transactions' => $transactions,
            'quality' => [
                'cut_at_today' => Carbon::parse($end)->gt($today),
                'one_offs' => $cur['one_off_rows'],
                'comma_amounts' => DB::table('expense_calculations')->whereBetween('date', [$s->toDateString(), $e->toDateString()])->where('amount', 'like', '%,%')->count(),
                'months_without_entries' => $cur['empty_months'],
            ],
        ];
        $ctx['insights'] = $run ? self::insights($ctx) : null;
        return $ctx;
    }

    /** Ledger totals for a date range (transfers between own accounts are never in the ledger). */
    public static function totals(Carbon $a, Carbon $b): array
    {
        $m = LedgerSeries::monthly($a->toDateString(), $b->toDateString());
        $oneOff = array_flip(LedgerSeries::oneOffIds());
        return [
            'income' => round(array_sum(array_column($m, 'income')), 2),
            'expense' => round(array_sum(array_column($m, 'expense')), 2),
            'core' => round(array_sum(array_column($m, 'core')), 2),
            'one_off' => round(array_sum(array_column($m, 'one_off')), 2),
            'one_off_rows' => DB::table('expense_calculations')->whereBetween('date', [$a->toDateString(), $b->toDateString()])->pluck('id')->filter(fn($id) => isset($oneOff[$id]))->count(),
            'empty_months' => count(array_filter($m, fn($x) => $x['rows'] === 0)),
        ];
    }

    /** Explanation for this period, cached per analytics run so the AI is asked once per period. */
    private static function insights(array $ctx): array
    {
        $report = "period:{$ctx['from']}:{$ctx['to']}";
        $cached = DB::table('analytics_insights')->where('run_id', $ctx['run'])->where('report', $report)->first();
        if ($cached) {
            return ['source' => $cached->source, 'model' => $cached->model, 'rejected' => $cached->rejected,
                'insights' => json_decode($cached->insights, true), 'actions' => json_decode($cached->actions, true)];
        }

        $c = $ctx['current'];
        $metrics = ['period' => [$ctx['from'], $ctx['to']], 'income' => round($c['income']), 'expense' => round($c['expense']),
            'everyday_spending' => round($c['core']), 'one_off_spending' => round($c['one_off'])];
        foreach ($ctx['compare'] as $k => $r) {
            $metrics['compared_with'][$k] = ['expense' => round($r['expense']), 'expense_change_percent' => $r['expense_change_percent'],
                'expense_change_after_inflation_percent' => $r['expense_real_change_percent'],
                'income' => round($r['income']), 'income_change_percent' => $r['income_change_percent'],
                'income_change_after_inflation_percent' => $r['income_real_change_percent']];
        }
        if ($ctx['unusual']) {
            $metrics['spending_unusual_for_this_month'] = $ctx['unusual'];
        }
        $metrics['unusual_transactions'] = count($ctx['transactions']);

        $t = [sprintf('Between %s and %s you earned %s and the ledger shows %s of expenses.', $ctx['from'], $ctx['to'], number_format($metrics['income']), number_format($metrics['expense']))];
        if ($metrics['one_off_spending'] > 0) {
            $t[] = sprintf('One-off purchases account for %s; everyday spending was %s.', number_format($metrics['one_off_spending']), number_format($metrics['everyday_spending']));
        }
        $words = ['previous_period' => 'the previous period', 'same_period_last_year' => 'the same period last year', 'average_12_months' => 'the 12-month average'];
        foreach ($words as $k => $w) {
            $p = $metrics['compared_with'][$k]['expense_change_percent'] ?? null;
            if ($p !== null) {
                $t[] = sprintf('Expenses were %d%% %s %s (%s).', abs($p), $p >= 0 ? 'above' : 'below', $w, number_format($metrics['compared_with'][$k]['expense']));
            }
        }
        if ($ctx['unusual']) {
            $t[] = sprintf('This month\'s spending is unusual: about %s is typical.', number_format($ctx['unusual']['usual']));
        }
        $a = [$metrics['unusual_transactions'] > 0
            ? sprintf('Review the %d unusual transactions listed below.', $metrics['unusual_transactions'])
            : 'No single transaction stands out; compare the largest categories with last year.'];

        $e = ReportExplainer::explain('period', $metrics, ['insights' => array_slice($t, 0, 5), 'actions' => $a]);
        DB::table('analytics_insights')->insert(['run_id' => $ctx['run'], 'report' => $report, 'source' => $e['source'], 'model' => $e['model'],
            'metrics' => json_encode($metrics), 'insights' => json_encode($e['insights']), 'actions' => json_encode($e['actions']), 'rejected' => $e['rejected'],
            'created_at' => now(), 'updated_at' => now()]);
        return $e;
    }
}
