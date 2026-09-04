<?php

namespace App\Services\AI;

use App\Services\InsightEngine;
use Carbon\Carbon;

/**
 * Template-based summary sentences. Starts from InsightEngine's existing
 * output (reused, not re-derived) and appends sentences this app doesn't
 * already produce (health score, forecast, top anomaly), in the same
 * {type, message} shape so the front end renders both origins identically.
 */
class NaturalLanguageGenerator
{
    public static function generate(Carbon $start, Carbon $end, array $context): array
    {
        $insights = InsightEngine::forPeriod($start, $end);

        if (!empty($context['health_score'])) {
            $health = $context['health_score'];
            $insights[] = [
                'type' => $health['score'] >= 60 ? 'success' : ($health['score'] >= 40 ? 'warning' : 'danger'),
                'message' => sprintf('Your financial health score is %d/100 (%s).', $health['score'], $health['label']),
            ];
        }

        $nextMonthForecast = $context['forecast']['forecast']['forecast'][0] ?? null;
        if ($nextMonthForecast !== null) {
            $insights[] = [
                'type' => 'info',
                'message' => sprintf(
                    "Based on recent trends, next month's spending is projected around %s.",
                    number_format(max($nextMonthForecast, 0), 2)
                ),
            ];
        }

        if (!empty($context['top_anomaly'])) {
            $anomaly = $context['top_anomaly'];
            $insights[] = [
                'type' => 'warning',
                'message' => sprintf(
                    '%s is the biggest outlier right now, running %.0f%% above its typical level.',
                    $anomaly['category'],
                    $anomaly['variance']
                ),
            ];
        }

        return $insights;
    }
}
