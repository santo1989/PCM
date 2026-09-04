<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ExpenseCalculation;
use App\Services\FinancialAnalyticsService;
use App\Services\InsightEngine;
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
     * Interactive Dashboard budget-exceeded alert banner.
     */
    public function budgetAlerts(Request $request)
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

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
        return view('backend.reports.cost_optimisation', $this->buildCostOptimisationData());
    }

    /** Same data as costOptimisation(), as JSON — polled every 30s by that page for a live feel. */
    public function costOptimisationData()
    {
        return response()->json($this->buildCostOptimisationData());
    }

    private function buildCostOptimisationData(): array
    {
        $year = (int) now()->year;
        $month = (int) now()->month;

        $paceRows = collect($this->analytics->livePace($year, $month))
            ->filter(fn($row) => $row['variance_percent'] >= 15)
            ->map(function ($row) {
                $row['suggestion'] = sprintf(
                    '%s is on pace for %s this month (%.0f%% above its usual daily rate over the last %d day%s). At the historical rate it would land around %s instead.',
                    $row['category_name'],
                    number_format($row['projected_month_total'], 2),
                    $row['variance_percent'],
                    $row['days_elapsed'],
                    $row['days_elapsed'] === 1 ? '' : 's',
                    number_format($row['historical_daily_pace'] * now()->daysInMonth, 2)
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

        return [
            'paceRows' => $paceRows,
            'totalProjectedOverspend' => array_sum(array_map(
                fn($row) => max($row['projected_month_total'] - $row['historical_daily_pace'] * now()->daysInMonth, 0),
                $paceRows
            )),
            'suggestions' => $anomalySuggestions,
            'totalPotentialSaving' => array_sum(array_column($anomalySuggestions, 'potential_saving')),
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Predictive Budget page — next month's per-category budget from a linear
     * forecast over each category's last 6 months, shown alongside the
     * existing reduction-factor approach on Budget Projection (not a
     * replacement for it).
     */
    public function predictiveBudget()
    {
        $categories = Category::where('types', 'EXPENSE')->get();
        $rows = [];

        foreach ($categories as $category) {
            $monthlyTotals = [];
            for ($i = 6; $i >= 1; $i--) {
                $cursor = now()->subMonths($i);
                $monthlyTotals[] = (float) ExpenseCalculation::where('types', 'EXPENSE')
                    ->where('category_id', $category->id)
                    ->whereYear('date', $cursor->year)->whereMonth('date', $cursor->month)
                    ->sum('amount');
            }

            if (array_sum($monthlyTotals) <= 0) {
                continue;
            }

            // History ends last month, so step 1 would predict the current
            // (already partially-actual) month — step 2 is the first fully
            // future month, which is what "next month's budget" should mean.
            $forecast = $this->analytics->linearForecast($monthlyTotals, 2);

            $rows[] = [
                'category' => $category->name,
                'last_6_months' => $monthlyTotals,
                'predicted_next_month' => max($forecast['forecast'][1], 0),
                'lower' => max($forecast['lower'][1], 0),
                'upper' => max($forecast['upper'][1], 0),
            ];
        }

        usort($rows, fn($a, $b) => $b['predicted_next_month'] <=> $a['predicted_next_month']);

        return view('backend.reports.predictive_budget', [
            'rows' => $rows,
            'totalPredicted' => array_sum(array_column($rows, 'predicted_next_month')),
        ]);
    }
}
