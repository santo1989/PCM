<?php

namespace App\Services\Analytics;

use App\Services\FinancialAnalyticsService;
use Carbon\Carbon;

/**
 * Reshapes FinancialAnalyticsService::anomalies()/livePace() into one
 * unified, severity-classified alert list for the dashboard — detection
 * itself (IQR/p90, pace-vs-history) lives in FinancialAnalyticsService, this
 * class only reshapes and phrases the results.
 */
class AnomalyDetector
{
    private const VARIANCE_FLOOR = 15.0;

    public static function detect(int $year, int $month): array
    {
        $analytics = new FinancialAnalyticsService();
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        $monthEnd = collect($analytics->anomalies($year, $month))
            ->filter(fn($row) => $row['over_percent'] >= self::VARIANCE_FLOOR)
            ->map(fn($row) => [
                'category' => $row['category_name'],
                'current' => $row['current'],
                'typical' => $row['p90_historical'],
                'variance' => $row['over_percent'],
                'severity' => self::classifySeverity($row['over_percent']),
                'suggestion' => sprintf(
                    '%s is running %.0f%% above its typical month. Bringing it back toward its usual level would free up about %s.',
                    $row['category_name'],
                    $row['over_percent'],
                    number_format(max($row['potential_saving'], 0), 2)
                ),
                'source' => 'month_end',
            ]);

        $livePace = collect($analytics->livePace($year, $month))
            ->filter(fn($row) => $row['variance_percent'] >= self::VARIANCE_FLOOR)
            ->map(fn($row) => [
                'category' => $row['category_name'],
                'current' => $row['month_to_date'],
                'typical' => $row['historical_daily_pace'] * $daysInMonth,
                'variance' => $row['variance_percent'],
                'severity' => self::classifySeverity($row['variance_percent']),
                'suggestion' => sprintf(
                    '%s is on pace for %s this month (%.0f%% above its usual daily rate over the last %d day%s). At the historical rate it would land around %s instead.',
                    $row['category_name'],
                    number_format($row['projected_month_total'], 2),
                    $row['variance_percent'],
                    $row['days_elapsed'],
                    $row['days_elapsed'] === 1 ? '' : 's',
                    number_format($row['historical_daily_pace'] * $daysInMonth, 2)
                ),
                'source' => 'live_pace',
            ]);

        return $monthEnd->concat($livePace)
            ->sortByDesc('variance')
            ->values()
            ->take(10)
            ->all();
    }

    private static function classifySeverity(float $variancePercent): string
    {
        return match (true) {
            $variancePercent >= 75 => 'critical',
            $variancePercent >= 40 => 'high',
            $variancePercent >= 15 => 'medium',
            default => 'low',
        };
    }
}
