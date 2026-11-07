<?php

namespace App\Services\AI;

use App\Services\Analytics\AnomalyDetector;
use App\Services\Analytics\ForecastingEngine;
use App\Services\Analytics\PatternAnalyzer;
use App\Services\Analytics\RecommendationsGenerator;
use App\Services\FinancialAnalysisService;
use App\Services\FinancialAnalyticsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Coordinates the Analytics/* helpers + InsightEngine into the dashboard's
 * "ai_insights" payload. Named MLPipeline per the requested file structure,
 * but this is explicitly a coordinator over built-in statistics — not real
 * machine learning, and it intentionally never calls
 * FinancialAnalysisService::getDashboardData() (which calls back into this
 * class) to avoid recursion; it only calls calculateFinancialHealthScore().
 */
class MLPipeline
{
    private const TTL_MINUTES = 10;

    /**
     * Pure: figures for the AI and rule-based recommendations from run()'s result, for the shared
     * insights panel (Yearly, Monthly, Monthly Investment, Cost Optimisation, Budget Projection, Predictive Budget).
     */
    public static function advice(array $ai): array
    {
        $o = $ai['outlook'] ?? null;
        $health = $ai['health_score'] ?? [];
        $m = ['health_score' => $health['score'] ?? null, 'health_label' => $health['label'] ?? null,
            'health_parts' => array_map(fn($c) => ['score' => $c['score'] ?? null, 'value' => $c['raw_value'] ?? null], $health['components'] ?? []),
            'unusual_spending' => array_map(fn($a) => ['category' => $a['category'], 'this_month' => round($a['current'] ?? 0), 'typical' => round($a['typical'] ?? 0)], array_slice($ai['anomalies'] ?? [], 0, 3))];
        if ($o) {
            $m['next_month'] = ['month' => $o['forecast']['labels'][0], 'income' => round($o['forecast']['income'][0]), 'expense' => round($o['forecast']['expense'][0]), 'net' => round($o['forecast']['net'][0])];
            $m['savings_ideas'] = array_map(fn($s) => ['category' => $s['category'], 'recent' => round($s['recent']), 'usual' => round($s['usual']), 'could_save' => round($s['potential'])], array_slice($o['savings'] ?? [], 0, 3));
        }

        $f = fn($v) => number_format($v);
        $ins = [];
        if ($m['health_score'] !== null) $ins[] = sprintf('Financial health is %s (%s out of 100).', $m['health_label'], $m['health_score']);
        if (isset($m['next_month'])) {
            $n = $m['next_month'];
            $ins[] = sprintf('%s is forecast at %s income and %s expense, leaving %s.', $n['month'], $f($n['income']), $f($n['expense']), $f($n['net']));
        }
        foreach (array_slice($m['unusual_spending'], 0, 2) as $a) $ins[] = sprintf('%s is %s this month against a typical %s.', $a['category'], $f($a['this_month']), $f($a['typical']));
        $act = [];
        foreach (array_slice($m['savings_ideas'] ?? [], 0, 2) as $s) $act[] = sprintf('Bring %s back from %s to about %s a month to save %s.', $s['category'], $f($s['recent']), $f($s['usual']), $f($s['could_save']));
        if (isset($m['next_month']) && $m['next_month']['net'] < 0) $act[] = sprintf('The forecast shows a %s shortfall: postpone non-essential spending.', $f(abs($m['next_month']['net'])));
        if (!$act) $act[] = 'Spending is within its usual range: keep moving the surplus to savings each month.';
        if (!$ins) $ins[] = 'Not enough history yet for a forecast.';

        return ['metrics' => $m, 'template' => ['insights' => array_slice($ins, 0, 5), 'actions' => array_slice($act, 0, 3)]];
    }

    public static function run(Carbon $start, Carbon $end, ?int $categoryId = null): array
    {
        $key = 'financial_analysis:ai_insights:' . $start->format('Ymd') . '-' . $end->format('Ymd') . ':' . ($categoryId ?? 'all');

        return Cache::remember($key, now()->addMinutes(self::TTL_MINUTES), function () use ($start, $end, $categoryId) {
            $year = (int) $end->year;
            $month = (int) $end->month;

            $anomalies = AnomalyDetector::detect($year, $month);
            $forecastOverall = ForecastingEngine::forecastOverall(3);
            $forecastCategories = array_slice(ForecastingEngine::forecastAllCategories(2), 0, 5);

            $dayOfWeek = PatternAnalyzer::dayOfWeekSpending($start, $end);
            $ruleTrend = PatternAnalyzer::ruleTrend(6);
            $seasonal = PatternAnalyzer::seasonalNotes();

            $service = new FinancialAnalysisService(new FinancialAnalyticsService());
            $health = $service->calculateFinancialHealthScore();
            $budgetRows = (new FinancialAnalyticsService())->budgetUtilization($year, $month);

            $recommendations = RecommendationsGenerator::generate($anomalies, $budgetRows, $health['components']);

            $summary = NaturalLanguageGenerator::generate($start, $end, [
                'health_score' => $health,
                'forecast' => $forecastOverall,
                'top_anomaly' => $anomalies[0] ?? null,
            ]);

            return [
                'summary' => $summary,
                'health_score' => $health,
                'anomalies' => $anomalies,
                'forecast' => [
                    'overall' => $forecastOverall,
                    'by_category' => $forecastCategories,
                ],
                'patterns' => [
                    'day_of_week' => $dayOfWeek,
                    'rule_trend' => $ruleTrend,
                    'seasonal_notes' => $seasonal,
                ],
                'recommendations' => $recommendations,
                // Range-driven income/expense/net forecast, budgets and cost-cutting ideas.
                'outlook' => ForecastingEngine::rangeOutlook($start, $end),
                'generated_at' => now()->toDateTimeString(),
            ];
        });
    }
}
