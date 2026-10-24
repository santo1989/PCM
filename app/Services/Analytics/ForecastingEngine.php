<?php

namespace App\Services\Analytics;

use App\Models\Category;
use App\Models\ExpenseCalculation;
use App\Services\FinancialAnalyticsService;
use Illuminate\Support\Facades\DB;

/**
 * Per-category and overall expense forecasts. History collection mirrors
 * AnalyticsController::budgetForecast()/predictiveBudget(); the regression
 * itself is FinancialAnalyticsService::linearForecast() (least-squares, no
 * ML library) — this class only assembles the history windows and shapes.
 */
class ForecastingEngine
{
    private const HISTORY_MONTHS = 6;

    public static function forecastOverall(int $periodsAhead = 3): array
    {
        $analytics = new FinancialAnalyticsService();
        $history = [];

        for ($i = self::HISTORY_MONTHS; $i >= 1; $i--) {
            $cursor = now()->subMonths($i);
            $history[] = [
                'label' => $cursor->format('M Y'),
                'total' => (float) ExpenseCalculation::where('types', 'EXPENSE')
                    ->whereYear('date', $cursor->year)->whereMonth('date', $cursor->month)
                    ->sum('amount'),
            ];
        }

        $forecast = $analytics->linearForecast(array_column($history, 'total'), $periodsAhead);

        $forecastLabels = [];
        for ($i = 0; $i < $periodsAhead; $i++) {
            $forecastLabels[] = now()->copy()->addMonths($i)->format('M Y');
        }

        return [
            'history' => $history,
            'forecast_labels' => $forecastLabels,
            'forecast' => $forecast,
        ];
    }

    public static function forecastCategory(int $categoryId, int $periodsAhead = 3): array
    {
        $analytics = new FinancialAnalyticsService();
        $category = Category::find($categoryId);
        $monthlyTotals = [];

        for ($i = self::HISTORY_MONTHS; $i >= 1; $i--) {
            $cursor = now()->subMonths($i);
            $monthlyTotals[] = (float) ExpenseCalculation::where('types', 'EXPENSE')
                ->where('category_id', $categoryId)
                ->whereYear('date', $cursor->year)->whereMonth('date', $cursor->month)
                ->sum('amount');
        }

        $forecast = $analytics->linearForecast($monthlyTotals, $periodsAhead);

        return [
            'category_id' => $categoryId,
            'category_name' => $category->name ?? 'Unknown',
            'history' => $monthlyTotals,
            'forecast' => array_map(fn($v) => max($v, 0), $forecast['forecast']),
            'lower' => array_map(fn($v) => max($v, 0), $forecast['lower']),
            'upper' => array_map(fn($v) => max($v, 0), $forecast['upper']),
        ];
    }

    /**
     * Forecast + budget + cost-cutting outlook for a selected date range, so every
     * report's AI block follows the user's range instead of "last 6 months from today".
     * History = the months the range covers (at least MIN_HISTORY, at most 24, ending
     * at the range end), from one grouped query.
     */
    public static function rangeOutlook(\Carbon\Carbon $start, \Carbon\Carbon $end, int $periodsAhead = 3): array
    {
        $analytics = new FinancialAnalyticsService();
        $last = $end->copy()->startOfMonth();
        $first = $start->copy()->startOfMonth();
        $months = min(max($first->diffInMonths($last) + 1, 6), 24);
        $first = $last->copy()->subMonthsNoOverflow($months - 1);

        $rows = ExpenseCalculation::whereIn('types', ['INCOME', 'EXPENSE'])
            ->whereBetween('date', [$first->toDateString(), $last->copy()->endOfMonth()->toDateString()])
            ->groupBy(DB::raw("DATE_FORMAT(date, '%Y-%m')"), 'types', 'category_id')
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as ym, types, category_id, SUM(amount) as total")
            ->get();

        $labels = [];
        $keys = [];
        for ($i = 0; $i < $months; $i++) {
            $m = $first->copy()->addMonthsNoOverflow($i);
            $keys[] = $m->format('Y-m');
            $labels[] = $m->format('M Y');
        }
        // Index once: [type][category|'all'][ym] => total (filtering the collection per lookup was O(n) each).
        $index = [];
        foreach ($rows as $row) {
            $index[$row->types]['all'][$row->ym] = ($index[$row->types]['all'][$row->ym] ?? 0) + (float) $row->total;
            $index[$row->types][$row->category_id][$row->ym] = ($index[$row->types][$row->category_id][$row->ym] ?? 0) + (float) $row->total;
        }
        $series = fn($type, $categoryId = null) => array_map(
            fn($k) => (float) ($index[$type][$categoryId ?? 'all'][$k] ?? 0),
            $keys
        );

        $income = $series('INCOME');
        $expense = $series('EXPENSE');
        $fIncome = $analytics->linearForecast($income, $periodsAhead);
        $fExpense = $analytics->linearForecast($expense, $periodsAhead);
        $clamp = fn(array $v) => array_map(fn($x) => max($x, 0), $v);

        $forecastLabels = [];
        for ($i = 1; $i <= $periodsAhead; $i++) {
            $forecastLabels[] = $last->copy()->addMonthsNoOverflow($i)->format('M Y');
        }
        $incomeNext = $clamp($fIncome['forecast']);
        $expenseNext = $clamp($fExpense['forecast']);

        // Monthly trend as % of the average month (positive = rising).
        $trend = fn(array $values, float $slope) => ($avg = array_sum($values) / max(count($values), 1)) > 0 ? $slope / $avg * 100 : 0;

        $budgets = [];
        $savings = [];
        $names = Category::where('types', 'EXPENSE')->pluck('name', 'id');
        foreach ($names as $id => $name) {
            $history = $series('EXPENSE', $id);
            if (array_sum($history) <= 0) {
                continue;
            }
            $next = max($analytics->linearForecast($history, 1)['forecast'][0], 0);
            $sorted = $history;
            sort($sorted);
            $median = $sorted[intdiv(count($sorted), 2)];
            $recent = array_sum(array_slice($history, -3)) / min(3, count($history));
            $average = array_sum($history) / count($history);

            $budgets[] = [
                'category' => $name,
                'average' => $average,
                'forecast' => $next,
                // Budget: the lower of forecast and average, so the plan nudges spending down.
                'suggested' => ceil(min($next, $average) / 100) * 100,
            ];
            if ($median > 0 && $recent > $median * 1.15) {
                $savings[] = [
                    'category' => $name,
                    'recent' => $recent,
                    'usual' => $median,
                    'potential' => $recent - $median,
                ];
            }
        }
        usort($budgets, fn($a, $b) => $b['suggested'] <=> $a['suggested']);
        usort($savings, fn($a, $b) => $b['potential'] <=> $a['potential']);

        return [
            'window' => ['from' => $labels[0], 'to' => end($labels), 'months' => $months],
            'history' => ['labels' => $labels, 'income' => $income, 'expense' => $expense],
            'forecast' => [
                'labels' => $forecastLabels,
                'income' => $incomeNext,
                'expense' => $expenseNext,
                'net' => array_map(fn($i, $e) => $i - $e, $incomeNext, $expenseNext),
            ],
            'trend' => [
                'income' => $trend($income, $fIncome['slope']),
                'expense' => $trend($expense, $fExpense['slope']),
            ],
            'budgets' => $budgets,
            'savings' => array_slice($savings, 0, 6),
            'savings_total' => array_sum(array_column($savings, 'potential')),
        ];
    }

    public static function forecastAllCategories(int $periodsAhead = 2): array
    {
        $analytics = new FinancialAnalyticsService();
        $months = [];
        for ($i = self::HISTORY_MONTHS; $i >= 1; $i--) {
            $months[] = now()->subMonths($i)->format('Y-m');
        }

        // Same history as forecastCategory(), for every category in one grouped query.
        $totals = ExpenseCalculation::where('types', 'EXPENSE')
            ->whereIn(DB::raw("DATE_FORMAT(date, '%Y-%m')"), $months)
            ->groupBy('category_id', DB::raw("DATE_FORMAT(date, '%Y-%m')"))
            ->selectRaw("category_id, DATE_FORMAT(date, '%Y-%m') as ym, SUM(amount) as total")
            ->get()
            ->groupBy('category_id')
            ->map(fn($g) => $g->pluck('total', 'ym'));

        $rows = [];
        foreach (Category::where('types', 'EXPENSE')->get() as $category) {
            $history = array_map(fn($ym) => (float) ($totals[$category->id][$ym] ?? 0), $months);
            if (array_sum($history) <= 0) {
                continue;
            }

            $forecast = $analytics->linearForecast($history, $periodsAhead);
            $rows[] = [
                'category_id' => $category->id,
                'category_name' => $category->name,
                'history' => $history,
                'forecast' => array_map(fn($v) => max($v, 0), $forecast['forecast']),
                'lower' => array_map(fn($v) => max($v, 0), $forecast['lower']),
                'upper' => array_map(fn($v) => max($v, 0), $forecast['upper']),
            ];
        }

        usort($rows, fn($a, $b) => ($b['forecast'][0] ?? 0) <=> ($a['forecast'][0] ?? 0));

        return $rows;
    }
}
