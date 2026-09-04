<?php

namespace App\Services;

use App\Models\Category;
use App\Models\ExpenseCalculation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Rule-based (deterministic, no external AI) natural-language-style insights
 * about savings vs. expense, for pages that don't already have their own
 * bespoke analysis section (the 5 report pages built in a prior session keep
 * their existing $analysis blocks untouched — this is additive, not a
 * replacement).
 *
 * Every method returns an array of ['type' => success|warning|info|danger, 'message' => string].
 */
class InsightEngine
{
    public static function forPeriod(Carbon $start, Carbon $end, ?int $categoryId = null): array
    {
        $cacheKey = 'insights:period:' . $start->format('Ymd') . '-' . $end->format('Ymd') . ':' . ($categoryId ?? 'all');

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($start, $end, $categoryId) {
            $insights = [];

            $incomeQuery = ExpenseCalculation::where('types', 'INCOME')->whereBetween('date', [$start, $end]);
            $expenseQuery = ExpenseCalculation::where('types', 'EXPENSE')->whereBetween('date', [$start, $end]);
            if ($categoryId) {
                $incomeQuery->where('category_id', $categoryId);
                $expenseQuery->where('category_id', $categoryId);
            }

            $income = (float) $incomeQuery->sum('amount');
            $expense = (float) $expenseQuery->sum('amount');

            if ($income <= 0 && $expense <= 0) {
                $insights[] = ['type' => 'info', 'message' => 'No transactions recorded for this period yet.'];
                return $insights;
            }

            if ($income > 0) {
                $savingsRate = (($income - $expense) / $income) * 100;
                if ($savingsRate >= 20) {
                    $insights[] = ['type' => 'success', 'message' => sprintf('Savings rate is %.1f%% — comfortably above the healthy 20%% target.', $savingsRate)];
                } elseif ($savingsRate >= 0) {
                    $insights[] = ['type' => 'warning', 'message' => sprintf('Savings rate is %.1f%%, below the healthy 20%% target.', $savingsRate)];
                } else {
                    $insights[] = ['type' => 'danger', 'message' => sprintf('Spending exceeded income by %.1f%% this period — a net loss.', abs($savingsRate))];
                }
            }

            // Period-over-period comparison against the immediately preceding period of equal length
            $days = (int) $start->diffInDays($end) + 1;
            $prevStart = $start->copy()->subDays($days);
            $prevEnd = $start->copy()->subDay();

            $prevExpenseQuery = ExpenseCalculation::where('types', 'EXPENSE')->whereBetween('date', [$prevStart, $prevEnd]);
            if ($categoryId) {
                $prevExpenseQuery->where('category_id', $categoryId);
            }
            $prevExpense = (float) $prevExpenseQuery->sum('amount');

            if ($prevExpense > 0 && $expense > 0) {
                $change = (($expense - $prevExpense) / $prevExpense) * 100;
                if (abs($change) >= 10) {
                    $insights[] = [
                        'type' => $change > 0 ? 'warning' : 'success',
                        'message' => sprintf('Expenses are %s%.1f%% compared to the previous %d-day period.', $change > 0 ? 'up ' : 'down ', abs($change), $days),
                    ];
                }
            }

            // Top expense category (only meaningful when not already scoped to one category)
            if (!$categoryId && $expense > 0) {
                $topCategoryRow = ExpenseCalculation::where('types', 'EXPENSE')
                    ->whereBetween('date', [$start, $end])
                    ->groupBy('category_id')
                    ->select('category_id', DB::raw('SUM(amount) as total'))
                    ->orderByDesc('total')
                    ->first();

                if ($topCategoryRow) {
                    $category = Category::find($topCategoryRow->category_id);
                    $share = ($topCategoryRow->total / $expense) * 100;
                    if ($category && $share >= 25) {
                        $insights[] = [
                            'type' => 'info',
                            'message' => sprintf('%s is the biggest expense category, making up %.0f%% of spending this period.', $category->name, $share),
                        ];
                    }
                }
            }

            return $insights;
        });
    }

    /**
     * Spend for one category this period vs. that category's own trailing average.
     */
    public static function forCategory(int $categoryId, Carbon $start, Carbon $end): array
    {
        $cacheKey = 'insights:category:' . $categoryId . ':' . $start->format('Ymd') . '-' . $end->format('Ymd');

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($categoryId, $start, $end) {
            $insights = [];

            $current = (float) ExpenseCalculation::where('category_id', $categoryId)
                ->where('types', 'EXPENSE')
                ->whereBetween('date', [$start, $end])
                ->sum('amount');

            $months = max((int) $start->diffInMonths($end), 1);
            $historyStart = $start->copy()->subMonths(6);
            $historyEnd = $start->copy()->subDay();

            $historyTotal = (float) ExpenseCalculation::where('category_id', $categoryId)
                ->where('types', 'EXPENSE')
                ->whereBetween('date', [$historyStart, $historyEnd])
                ->sum('amount');

            $historyMonths = max((int) $historyStart->diffInMonths($historyEnd), 1);
            $historyAvg = $historyTotal / $historyMonths;

            if ($historyAvg > 0 && $current > 0) {
                $periodAvg = $current / $months;
                $change = (($periodAvg - $historyAvg) / $historyAvg) * 100;
                if (abs($change) >= 20) {
                    $insights[] = [
                        'type' => $change > 0 ? 'warning' : 'success',
                        'message' => sprintf('Spending is %s%.0f%% compared to its 6-month average.', $change > 0 ? 'up ' : 'down ', abs($change)),
                    ];
                }
            }

            return $insights;
        });
    }

    /**
     * Cheap default entry point for dashboard-level pages with no explicit
     * date range in scope — defaults to the current calendar month.
     */
    public static function dashboardSummary(): array
    {
        return self::forPeriod(now()->startOfMonth(), now()->endOfMonth());
    }

    /**
     * Turns FinancialAnalyticsService's numbers (runway, projected balance,
     * anomalies) into the same natural-language-style cards used everywhere
     * else. This method owns the wording; FinancialAnalyticsService owns the math.
     */
    public static function financialHealth(): array
    {
        $cacheKey = 'insights:financial_health:' . now()->format('YmdH');

        return Cache::remember($cacheKey, now()->addMinutes(10), function () {
            $analytics = new FinancialAnalyticsService();
            $insights = [];

            $runway = $analytics->cashRunway();
            if (is_finite($runway)) {
                if ($runway < 30) {
                    $insights[] = ['type' => 'danger', 'message' => sprintf('At the current spending pace, your balance covers about %d days — worth building a bigger buffer soon.', (int) $runway)];
                } elseif ($runway < 90) {
                    $insights[] = ['type' => 'warning', 'message' => sprintf('Roughly %d days of runway left at the current spending pace.', (int) $runway)];
                } else {
                    $insights[] = ['type' => 'success', 'message' => sprintf('Healthy runway: about %d days of expenses covered at the current pace.', (int) $runway)];
                }
            }

            $current = $analytics->currentCashBalance();
            $projected = $analytics->projectedMonthEndBalance();
            if (abs($projected - $current) > 0.01) {
                $insights[] = [
                    'type' => $projected >= $current ? 'success' : 'warning',
                    'message' => sprintf('At this pace, month-end balance is projected around %s (currently %s).', number_format($projected, 2), number_format($current, 2)),
                ];
            }

            $anomalies = $analytics->anomalies((int) now()->year, (int) now()->month);
            foreach (array_slice($anomalies, 0, 2) as $anomaly) {
                $insights[] = [
                    'type' => 'warning',
                    'message' => sprintf('%s spending is %.0f%% above its typical month — worth trimming here first.', $anomaly['category_name'], $anomaly['over_percent']),
                ];
            }

            return $insights;
        });
    }

    /**
     * A single rotating tip for the Home page — deterministic (keyed by day of
     * year, not random), drawn from whatever real insights exist today, falling
     * back to a small pool of generic personal-finance tips when there's
     * nothing period-specific to say yet.
     */
    public static function tipOfTheDay(): string
    {
        $pool = array_merge(self::dashboardSummary(), self::financialHealth());

        $genericTips = [
            'Review your top spending category each week to catch drift early.',
            'Automate transfers to savings right after income lands — pay yourself first.',
            'Set a monthly budget per category and check it before big purchases.',
            'Small recurring subscriptions add up — audit them every few months.',
            'Compare this month to last month, not just to your budget — trends matter more than any single month.',
        ];

        if (empty($pool)) {
            return $genericTips[now()->dayOfYear % count($genericTips)];
        }

        return $pool[now()->dayOfYear % count($pool)]['message'];
    }
}
