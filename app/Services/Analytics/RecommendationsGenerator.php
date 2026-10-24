<?php

namespace App\Services\Analytics;

/**
 * Pure rule-based text from already-computed inputs (anomalies, budget rows,
 * health-score components) — no new queries here, just phrasing decisions.
 */
class RecommendationsGenerator
{
    public static function generate(array $anomalies, array $budgetRows, array $healthComponents): array
    {
        $recommendations = [];

        foreach ($anomalies as $anomaly) {
            if (!in_array($anomaly['severity'], ['high', 'critical'], true)) {
                continue;
            }

            $potentialImpact = max($anomaly['current'] - $anomaly['typical'], 0);

            $recommendations[] = [
                'priority' => $anomaly['severity'],
                'title' => "Rein in {$anomaly['category']} spending",
                'message' => sprintf(
                    'Reduce %s spending toward its typical level to save about %s/month.',
                    $anomaly['category'],
                    number_format($potentialImpact, 2)
                ),
                'potential_impact' => $potentialImpact,
            ];
        }

        foreach ($budgetRows as $row) {
            if ($row['utilization_percent'] <= 100) {
                continue;
            }

            $overspend = $row['actual'] - $row['projected'];

            $recommendations[] = [
                'priority' => $row['utilization_percent'] > 130 ? 'high' : 'medium',
                'title' => "Over budget: {$row['category_name']}",
                'message' => sprintf(
                    "You've exceeded your %s budget by %s; consider reallocating.",
                    $row['category_name'],
                    number_format($overspend, 2)
                ),
                'potential_impact' => $overspend,
            ];
        }

        if (($healthComponents['savings_rate']['score'] ?? 100) < 50) {
            $recommendations[] = [
                'priority' => 'medium',
                'title' => 'Boost your savings rate',
                'message' => sprintf(
                    'Your average savings rate is %.1f%% — moving it toward the healthy 20%% target would meaningfully improve your health score.',
                    $healthComponents['savings_rate']['raw_value'] ?? 0
                ),
                'potential_impact' => null,
            ];
        }

        if (($healthComponents['cash_runway']['score'] ?? 100) < 40) {
            $recommendations[] = [
                'priority' => 'high',
                'title' => 'Build a bigger cash buffer',
                'message' => sprintf(
                    'At the current spending pace your runway is about %s days — building toward 90 days would give you more breathing room.',
                    $healthComponents['cash_runway']['raw_value'] ?? 'a few'
                ),
                'potential_impact' => null,
            ];
        }

        if (($healthComponents['investment_growth']['score'] ?? 100) < 50) {
            $recommendations[] = [
                'priority' => 'medium',
                'title' => 'Contribute more consistently',
                'message' => sprintf(
                    'Only %d of the last 6 months had net positive investment contributions — automating a fixed monthly transfer would smooth this out.',
                    $healthComponents['investment_growth']['raw_value'] ?? 0
                ),
                'potential_impact' => null,
            ];
        }

        $priorityOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        usort($recommendations, fn($a, $b) => ($priorityOrder[$a['priority']] ?? 99) <=> ($priorityOrder[$b['priority']] ?? 99));

        return array_slice($recommendations, 0, 8);
    }
}
