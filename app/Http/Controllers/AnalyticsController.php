<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ExpenseCalculation;
use App\Services\AI\MLPipeline;
use App\Services\Analytics\Engine;
use App\Services\Analytics\LedgerSeries;
use App\Services\BudgetPredictor;
use App\Services\FinancialAnalyticsService;
use App\Services\InsightEngine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    private FinancialAnalyticsService $analytics;

    public function __construct(FinancialAnalyticsService $analytics)
    {
        $this->analytics = $analytics;
    }

    /**
     * Home page "Financial Health" widget — polled every 30s from the client.
     */
    public function homeKpis(Request $request)
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        $income = (float) ExpenseCalculation::where('types', 'INCOME')
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])->sum('amount');
        $expense = (float) ExpenseCalculation::where('types', 'EXPENSE')
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])->sum('amount');

        $topCategories = ExpenseCalculation::where('types', 'EXPENSE')
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->groupBy('category_id')
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->orderByDesc('total')
            ->limit(3)
            ->get();

        $categoryNames = Category::whereIn('id', $topCategories->pluck('category_id'))->pluck('name', 'id');

        $topCategoriesPayload = $topCategories->map(function ($row) use ($categoryNames, $expense) {
            return [
                'name' => $categoryNames[$row->category_id] ?? 'Unknown',
                'amount' => (float) $row->total,
                'percent' => $expense > 0 ? ((float) $row->total / $expense) * 100 : 0,
            ];
        });

        return response()->json([
            'balance' => $this->analytics->currentCashBalance(),
            'month_income' => $income,
            'month_expense' => $expense,
            'top_categories' => $topCategoriesPayload,
            'tip_of_the_day' => InsightEngine::tipOfTheDay(),
            'generated_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Budget Projection page — 6-month linear-regression forecast of total
     * monthly expense, with a naive confidence band.
     */
    public function budgetForecast(Request $request)
    {
        // 6 fully-completed months, excluding the current (still in-progress) month —
        // a partial month would drag the regression down artificially.
        $months = [];
        for ($i = 6; $i >= 1; $i--) {
            $cursor = now()->subMonths($i);
            $months[] = [
                'label' => $cursor->format('M Y'),
                'total' => (float) ExpenseCalculation::where('types', 'EXPENSE')
                    ->whereYear('date', $cursor->year)->whereMonth('date', $cursor->month)
                    ->sum('amount'),
            ];
        }

        $forecast = $this->analytics->linearForecast(array_column($months, 'total'), 3);

        // History ends last month, so the first forecast point IS the current
        // (in-progress) month, then the next two full months after it.
        return response()->json([
            'history' => $months,
            'forecast_labels' => [now()->format('M Y'), now()->addMonth()->format('M Y'), now()->addMonths(2)->format('M Y')],
            'forecast' => $forecast,
        ]);
    }

    /**
     * Budget Projection "Compare with actual" — actual vs. projected spend
     * for a given year/month, defaulting to the most recent month that has
     * ProjectedExpense rows to compare against.
     */
    public function compareActual(Request $request)
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        return response()->json([
            'year' => $year,
            'month' => $month,
            'rows' => $this->analytics->budgetUtilization($year, $month),
        ]);
    }

    /**
     * Budget Projection "Budget vs Actual" modal: one month (?month=YYYY-MM).
     */
    public function budgetVsActual(Request $request)
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->get('month'))
            ? Carbon::createFromFormat('Y-m-d', $request->get('month') . '-01')
            : now();

        return response()->json(BudgetPredictor::cached('compare', $month, fn() => (new BudgetPredictor())->compare($month)));
    }

    /**
     * Interactive Dashboard budget-exceeded alert banner.
     */
    public function budgetAlerts(Request $request)
    {
        // budgetUtilization() is inherently month-scoped (actual spend vs. that month's
        // ProjectedExpense rows) — a date range doesn't compose with it, so when a range
        // is given, the month it ends in is used, matching MLPipeline's convention.
        if ($request->filled('end_date')) {
            $end = Carbon::parse($request->get('end_date'));
            $year = (int) $end->year;
            $month = (int) $end->month;
        } else {
            $year = (int) $request->get('year', now()->year);
            $month = (int) $request->get('month', now()->month);
        }

        $rows = collect($this->analytics->budgetUtilization($year, $month))
            ->filter(fn($row) => $row['utilization_percent'] > 100)
            ->values();

        return response()->json($rows);
    }

    /**
     * Cost Optimisation page. Two layers, both real-time-computed (no stored
     * insights):
     *  - "pace" flags: this month's spend-per-day-so-far vs. each category's
     *    historical spend-per-day, meaningful from day 1 of the month (needs
     *    only 1 prior month with any spend, not 4+ full months).
     *  - "anomaly" flags: full-month total vs. the 90th percentile of that
     *    category's historical full months — a stricter, month-end-oriented
     *    check that supplements the pace flags once more of the month (and
     *    more historical months) exist.
     */
    public function costOptimisation()
    {
        return view('backend.reports.cost_optimisation', array_merge(
            $this->buildCostOptimisationData(),
            ['minDataDate' => ExpenseCalculation::min('date')]
        ));
    }

    /** Same data as costOptimisation(), as JSON — polled every 30s by that page for a live feel. */
    public function costOptimisationData()
    {
        return response()->json($this->buildCostOptimisationData());
    }

    private function buildCostOptimisationData(): array
    {
        // Date range replaces the old fixed "current month": the month it analyzes is
        // derived from the End Date (matching the same convention used elsewhere),
        // defaulting to the current month. livePace() already handles a past month
        // correctly (it only treats "today" as the elapsed cutoff for the real current
        // month), so no other change is needed there.
        $startDate = request('start_date', now()->startOfMonth()->toDateString());
        $endDate = request('end_date', now()->toDateString());

        $referenceDate = Carbon::parse($endDate);
        $year = (int) $referenceDate->year;
        $month = (int) $referenceDate->month;
        $daysInSelectedMonth = Carbon::create($year, $month, 1)->daysInMonth;

        $paceRows = collect($this->analytics->livePace($year, $month))
            ->filter(fn($row) => $row['variance_percent'] >= 15)
            ->map(function ($row) use ($daysInSelectedMonth) {
                $row['suggestion'] = sprintf(
                    '%s is on pace for %s this month (%.0f%% above its usual daily rate over the last %d day%s). At the historical rate it would land around %s instead.',
                    $row['category_name'],
                    number_format($row['projected_month_total'], 2),
                    $row['variance_percent'],
                    $row['days_elapsed'],
                    $row['days_elapsed'] === 1 ? '' : 's',
                    number_format($row['historical_daily_pace'] * $daysInSelectedMonth, 2)
                );
                return $row;
            })
            ->values()
            ->all();

        $anomalies = $this->analytics->anomalies($year, $month);
        $anomalySuggestions = array_map(function ($row) {
            $row['suggestion'] = sprintf(
                '%s is running %.0f%% above its typical month. Bringing it back toward its usual level would free up about %s.',
                $row['category_name'],
                $row['over_percent'],
                number_format(max($row['potential_saving'], 0), 2)
            );
            return $row;
        }, $anomalies);

        $aiInsights = MLPipeline::run(Carbon::create($year, $month, 1)->startOfMonth(), Carbon::create($year, $month, 1)->endOfMonth());

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'paceRows' => $paceRows,
            'totalProjectedOverspend' => array_sum(array_map(
                fn($row) => max($row['projected_month_total'] - $row['historical_daily_pace'] * $daysInSelectedMonth, 0),
                $paceRows
            )),
            'suggestions' => $anomalySuggestions,
            'totalPotentialSaving' => array_sum(array_column($anomalySuggestions, 'potential_saving')),
            'aiSummary' => $aiInsights['summary'],
            'aiRecommendations' => $aiInsights['recommendations'],
            'aiOutlook' => MLPipeline::run(Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay())['outlook'] ?? null,
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Predictive Budget page — next-month per-category budget from a linear
     * forecast over a history window, shown alongside the existing
     * reduction-factor approach on Budget Projection (not a replacement for it).
     *
     * Date range replaces the old fixed "last 6 completed months": Start/End Date
     * define the history window fed into the regression (defaulting to the 6
     * months up through today), and the forecast predicts the month right after
     * the window ends.
     */
    public function predictiveBudget()
    {
        $minDataDate = ExpenseCalculation::min('date');
        $startDate = request('start_date', now()->subMonths(6)->startOfMonth()->toDateString());
        $endDate = request('end_date', now()->toDateString());

        $historyMonths = [];
        $cursor = Carbon::parse($startDate)->startOfMonth();
        $lastMonth = Carbon::parse($endDate)->startOfMonth();
        while ($cursor->lte($lastMonth)) {
            $historyMonths[] = $cursor->copy();
            $cursor->addMonth();
        }
        if (empty($historyMonths)) {
            $historyMonths[] = $lastMonth->copy();
        }

        $categories = Category::where('types', 'EXPENSE')->get();
        $rows = [];

        // One grouped query for every category x month (was one query per pair).
        $totals = ExpenseCalculation::where('types', 'EXPENSE')
            ->whereBetween('date', [$historyMonths[0]->copy()->startOfMonth()->toDateString(), end($historyMonths)->copy()->endOfMonth()->toDateString()])
            ->groupBy('category_id', DB::raw("DATE_FORMAT(date, '%Y-%m')"))
            ->selectRaw("category_id, DATE_FORMAT(date, '%Y-%m') as ym, SUM(amount) as total")
            ->get()
            ->groupBy('category_id')
            ->map(fn($g) => $g->pluck('total', 'ym'));

        $target = end($historyMonths)->copy()->addMonthNoOverflow();
        $backtested = $this->backtestedForecasts($target);

        foreach ($categories as $category) {
            $monthlyTotals = [];
            foreach ($historyMonths as $m) {
                $monthlyTotals[] = (float) ($totals[$category->id][$m->format('Y-m')] ?? 0);
            }

            if (array_sum($monthlyTotals) <= 0) {
                continue;
            }

            $forecast = $this->analytics->linearForecast($monthlyTotals, 1);
            $linear = max($forecast['forecast'][0], 0);
            // Back-tested forecast (best model per category, one-offs excluded) replaces the linear trend;
            // the linear value stays visible for comparison.
            $b = $backtested[$category->id] ?? null;

            $rows[] = [
                'category' => $category->name,
                'last_6_months' => $monthlyTotals,
                'predicted_next_month' => $b ? $b['mean'] : $linear,
                'lower' => $b ? $b['lo80'] : max($forecast['lower'][0], 0),
                'upper' => $b ? $b['hi80'] : max($forecast['upper'][0], 0),
                'model' => $b['model'] ?? 'Linear trend',
                'mase' => $b['mase'] ?? null,
                'note' => $b['note'] ?? 'No back-tested model for this category; linear trend shown.',
                'linear_prediction' => $linear,
            ];
        }

        usort($rows, fn($a, $b) => $b['predicted_next_month'] <=> $a['predicted_next_month']);

        $aiInsights = MLPipeline::run(Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay());

        return view('backend.reports.predictive_budget', [
            'rows' => $rows,
            'targetMonth' => $target->format('F Y'),
            'totalLinear' => array_sum(array_column($rows, 'linear_prediction')),
            'totalPredicted' => array_sum(array_column($rows, 'predicted_next_month')),
            'aiInsights' => $aiInsights,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'minDataDate' => $minDataDate,
        ]);
    }

    /**
     * Per expense category: the best back-tested model's forecast for $target, trained on every
     * completed month before it (a partial current month would look like a drop). Cached until the
     * ledger changes. Returns category id => [mean, lo80, hi80, model, mase, note].
     */
    private function backtestedForecasts(Carbon $target): array
    {
        $lastCompleted = $target->copy()->subMonthNoOverflow()->min(now()->startOfMonth()->subMonthNoOverflow());
        $ahead = $lastCompleted->diffInMonths($target); // 1 = next month; the engine forecasts up to 3 ahead
        if ($ahead < 1 || $ahead > Engine::H) {
            return [];
        }
        $key = 'predictive_budget:' . $target->format('Y-m') . ':' . BudgetPredictor::signature();

        return \Illuminate\Support\Facades\Cache::remember($key, now()->addDay(), function () use ($lastCompleted, $ahead) {
            $to = $lastCompleted->copy()->endOfMonth()->toDateString();
            $months = LedgerSeries::months(LedgerSeries::firstDate(), $to);
            $flags = fn($ym) => (fn($f) => [$f['ramadan_days'] / 30, (int) $f['eid_fitr'], (int) $f['eid_adha']])(LedgerSeries::monthFlags($ym));
            $X = array_map($flags, $months);
            $Xf = [];
            for ($i = 1; $i <= Engine::H; $i++) {
                $Xf[] = $flags($lastCompleted->copy()->addMonthsNoOverflow($i)->format('Y-m'));
            }
            $out = [];
            foreach (LedgerSeries::categoryMonthly(null, $to)['categories'] as $id => $c) {
                if ($c['type'] !== 'EXPENSE') {
                    continue;
                }
                $y = array_values(array_map(fn($a, $o) => $a - $o, $c['months'], $c['one_off']));
                $r = Engine::runSeries("category:$id", $y, $X, $Xf, false);
                if ($r['forecast']) {
                    $f = $r['forecast'][$ahead - 1];
                    $mase = array_column($r['models'], 'mase', 'model')[$r['chosen']] ?? null;
                    $out[$id] = ['mean' => $f['mean'], 'lo80' => $f['lo80'], 'hi80' => $f['hi80'], 'model' => $r['chosen'],
                        'mase' => $mase !== null ? round($mase, 2) : null, 'note' => $r['note']];
                } elseif (isset($r['average'])) {
                    $out[$id] = ['mean' => $r['average'], 'lo80' => $r['average'], 'hi80' => $r['average'], 'model' => '12-month average', 'mase' => null, 'note' => $r['note']];
                }
            }
            return $out;
        });
    }
}
