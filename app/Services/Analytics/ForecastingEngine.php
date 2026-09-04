<?php

namespace App\Services\Analytics;

use App\Models\Category;
use App\Models\ExpenseCalculation;
use App\Services\FinancialAnalyticsService;

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

    public static function forecastAllCategories(int $periodsAhead = 2): array
    {
        $rows = [];

        foreach (Category::where('types', 'EXPENSE')->get() as $category) {
            $result = self::forecastCategory($category->id, $periodsAhead);

            if (array_sum($result['history']) <= 0) {
                continue;
            }

            $rows[] = $result;
        }

        usort($rows, fn($a, $b) => ($b['forecast'][0] ?? 0) <=> ($a['forecast'][0] ?? 0));

        return $rows;
    }
}
