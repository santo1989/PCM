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
                'generated_at' => now()->toDateTimeString(),
            ];
        });
    }
}
