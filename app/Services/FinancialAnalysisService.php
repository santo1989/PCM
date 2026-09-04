<?php

namespace App\Services;

use App\DataTables\FinancialAnalysisDataTable;
use App\Models\Category;
use App\Models\ExpenseCalculation;
use App\Models\HandCash;
use App\Services\AI\MLPipeline;
use App\Services\Analytics\PatternAnalyzer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Consolidates every widget on the Financial Analysis Dashboard. Does not
 * re-derive numbers FinancialAnalyticsService already owns (balance, burn
 * rate, budget utilization, anomalies, pace, runway, investment trend,
 * regression) — it composes/reshapes those plus a handful of new small
 * aggregates (category/rule/account breakdowns, day-of-week, month-over-month)
 * that don't exist anywhere else in the app.
 */
class FinancialAnalysisService
{
    private const TTL_MINUTES = 10;

    /**
     * No market-valuation data source exists in this app (only contribution/
     * withdrawal transactions), so any "growth" projection needs an assumed
     * rate — documented everywhere it's used rather than presented as real.
     */
    private const ASSUMED_ANNUAL_RETURN_RATE = 0.07;

    private FinancialAnalyticsService $analytics;

    public function __construct(FinancialAnalyticsService $analytics)
    {
        $this->analytics = $analytics;
    }

    /**
     * Single funnel every other method goes through for filter semantics, so
     * "this month" / "last 3 months" / custom range mean the same thing
     * everywhere on the dashboard.
     */
    public function resolveDateRange(array $filters): array
    {
        $period = $filters['period'] ?? 'this_month';

        if ($period === 'custom' && !empty($filters['start_date']) && !empty($filters['end_date'])) {
            $start = Carbon::parse($filters['start_date'])->startOfDay();
            $end = Carbon::parse($filters['end_date'])->endOfDay();
            $label = $start->format('M j, Y') . ' - ' . $end->format('M j, Y');
        } else {
            [$start, $end, $label] = match ($period) {
                'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth(), 'Last Month'],
                'last_3_months' => [now()->subMonths(2)->startOfMonth(), now()->endOfMonth(), 'Last 3 Months'],
                'last_6_months' => [now()->subMonths(5)->startOfMonth(), now()->endOfMonth(), 'Last 6 Months'],
                'last_12_months' => [now()->subMonths(11)->startOfMonth(), now()->endOfMonth(), 'Last 12 Months'],
                'this_year' => [now()->startOfYear(), now()->endOfYear(), 'This Year'],
                'last_year' => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear(), 'Last Year'],
                default => [now()->startOfMonth(), now()->endOfMonth(), 'This Month'],
            };
        }

        return [
            'start' => $start,
            'end' => $end,
            'label' => $label,
            'year' => (int) $end->year,
            'month' => (int) $end->month,
        ];
    }

    /** Top-level orchestrator behind GET /financial-analysis/data. */
    public function getDashboardData(array $filters): array
    {
        $cacheKey = 'financial_analysis:dashboard:' . md5(json_encode($this->normalizeFilters($filters)));

        return Cache::remember($cacheKey, now()->addMinutes(self::TTL_MINUTES), function () use ($filters) {
            $range = $this->resolveDateRange($filters);
            $start = $range['start'];
            $end = $range['end'];
            $categoryId = !empty($filters['category_id']) ? (int) $filters['category_id'] : null;

            return [
                'range' => [
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                    'label' => $range['label'],
                ],
                'kpis' => $this->buildKpiSet($start, $end),
                'trend' => $this->getTrendData($start, $end),
                'category_breakdown' => $this->getCategoryBreakdown($start, $end, $categoryId),
                'spending_by_rule' => $this->getSpendingByRule($start, $end),
                'cash_by_account' => $this->getCashByAccount(),
                'budget' => $this->getBudgetWidget($range['year'], $range['month']),
                'investment' => $this->getInvestmentWidget(),
                'day_of_week' => $this->getDayOfWeekSpending($start, $end),
                'monthly_comparison' => $this->getMonthlyComparison($range['year'], $range['month']),
                'health_score' => $this->calculateFinancialHealthScore(),
                'transactions' => (new FinancialAnalysisDataTable(array_merge($filters, [
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                ])))->rows(),
                'ai_insights' => MLPipeline::run($start, $end, $categoryId),
                'generated_at' => now()->toDateTimeString(),
            ];
        });
    }

    /** Behind GET /financial-analysis/kpi/{type} — a single lightweight card, pollable independently. */
    public function getKPI(string $type, array $filters): array
    {
        $key = 'financial_analysis:kpi:' . $type . ':' . md5(json_encode($this->normalizeFilters($filters)));

        return Cache::remember($key, now()->addMinutes(5), function () use ($type, $filters) {
            $range = $this->resolveDateRange($filters);
            $start = $range['start'];
            $end = $range['end'];

            $value = match ($type) {
                'balance' => $this->analytics->currentCashBalance(),
                'projected_balance' => $this->analytics->projectedMonthEndBalance(),
                'month_income' => $this->sumByType('INCOME', $start, $end),
                'month_expense' => $this->sumByType('EXPENSE', $start, $end),
                'month_net' => $this->sumByType('INCOME', $start, $end) - $this->sumByType('EXPENSE', $start, $end),
                'savings_rate' => $this->analytics->savingsRateTrend(1)['average'],
                'burn_rate' => $this->analytics->burnRate($start, $end),
                'runway' => $this->analytics->cashRunway(),
                'health_score' => $this->calculateFinancialHealthScore()['score'],
                default => throw new \InvalidArgumentException("Unknown KPI type: {$type}"),
            };

            return [
                'type' => $type,
                'value' => is_float($value) && is_infinite($value) ? null : $value,
                'formatted' => is_float($value) && is_infinite($value) ? 'Unlimited' : (is_numeric($value) ? number_format((float) $value, 2) : (string) $value),
                'generated_at' => now()->toDateTimeString(),
            ];
        });
    }

    /** Full KPI set for a resolved range — used by the export (a single getKPI() call only returns one metric). */
    public function getKpiSummary(array $filters): array
    {
        $range = $this->resolveDateRange($filters);
        return $this->buildKpiSet($range['start'], $range['end']);
    }

    public function getTrendData(Carbon $start, Carbon $end): array
    {
        $months = [];
        $cursor = $start->copy()->startOfMonth();
        $lastMonth = $end->copy()->startOfMonth();

        while ($cursor->lte($lastMonth)) {
            $totals = $this->monthTotals($cursor->year, $cursor->month);
            $months[] = array_merge(['label' => $cursor->format('M Y')], $totals, [
                'savings_rate' => $totals['income'] > 0 ? (($totals['income'] - $totals['expense']) / $totals['income']) * 100 : 0,
            ]);
            $cursor->addMonth();
        }

        $forecast = $this->analytics->linearForecast(array_column($months, 'expense'), 3);
        $forecastLabels = [];
        $forecastCursor = $lastMonth->copy();
        for ($i = 1; $i <= 3; $i++) {
            $forecastCursor->addMonth();
            $forecastLabels[] = $forecastCursor->format('M Y');
        }

        return [
            'labels' => array_column($months, 'label'),
            'income' => array_column($months, 'income'),
            'expense' => array_column($months, 'expense'),
            'net' => array_column($months, 'net'),
            'savings_rate' => array_column($months, 'savings_rate'),
            'forecast' => [
                'labels' => $forecastLabels,
                'expense' => $forecast['forecast'],
                'lower' => $forecast['lower'],
                'upper' => $forecast['upper'],
            ],
        ];
    }

    public function getCategoryBreakdown(Carbon $start, Carbon $end, ?int $categoryId = null): array
    {
        $query = ExpenseCalculation::where('types', 'EXPENSE')
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')]);

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $rows = $query->groupBy('category_id')
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->orderByDesc('total')
            ->get();

        $total = (float) $rows->sum('total');
        $categoryNames = Category::whereIn('id', $rows->pluck('category_id'))->pluck('name', 'id');

        return $rows->map(function ($row) use ($categoryNames, $total) {
            return [
                'category_id' => $row->category_id,
                'category_name' => $categoryNames[$row->category_id] ?? 'Unknown',
                'amount' => (float) $row->total,
                'percent' => $total > 0 ? ((float) $row->total / $total) * 100 : 0,
            ];
        })->values()->all();
    }

    /** Needs/Wants/Savings split for the period (the 50/30/20-style `rules` column on ExpenseCalculation). */
    public function getSpendingByRule(Carbon $start, Carbon $end): array
    {
        $rows = ExpenseCalculation::where('types', 'EXPENSE')
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->groupBy('rules')
            ->select('rules', DB::raw('SUM(amount) as total'))
            ->get();

        $total = (float) $rows->sum('total');

        return $rows->map(fn($row) => [
            'rule' => $row->rules,
            'total' => (float) $row->total,
            'percent' => $total > 0 ? ((float) $row->total / $total) * 100 : 0,
        ])->sortByDesc('total')->values()->all();
    }

    /** Net (all-time) balance per HandCash account name — not scoped to the selected period. */
    public function getCashByAccount(): array
    {
        $rows = HandCash::groupBy('name')
            ->select('name', DB::raw("SUM(CASE WHEN types = 'SAVE' THEN amount ELSE -amount END) as balance"))
            ->get();

        return $rows->map(fn($row) => [
            'account' => $row->name,
            'balance' => (float) $row->balance,
        ])->sortByDesc('balance')->values()->all();
    }

    public function getBudgetWidget(int $year, int $month): array
    {
        $rows = $this->analytics->budgetUtilization($year, $month);

        $totalProjected = array_sum(array_column($rows, 'projected'));
        $totalActual = array_sum(array_column($rows, 'actual'));

        return [
            'rows' => $rows,
            'total_projected' => $totalProjected,
            'total_actual' => $totalActual,
            'overall_utilization_percent' => $totalProjected > 0 ? ($totalActual / $totalProjected) * 100 : 0,
            'over_budget_count' => count(array_filter($rows, fn($row) => $row['utilization_percent'] > 100)),
        ];
    }

    public function getInvestmentWidget(): array
    {
        $key = 'financial_analysis:investment_widget:' . now()->format('Ymd');

        return Cache::remember($key, now()->addMinutes(self::TTL_MINUTES), function () {
            $contributionTrend = $this->analytics->investmentContributionTrend(12);

            $allocationRows = HandCash::whereNotNull('rules')
                ->groupBy('rules')
                ->select('rules', DB::raw("SUM(CASE WHEN types = 'SAVE' THEN amount ELSE -amount END) as balance"))
                ->get()
                ->filter(fn($row) => (float) $row->balance > 0);

            $principal = (float) $allocationRows->sum('balance');

            $assetAllocation = $allocationRows->map(fn($row) => [
                'rule' => $row->rules,
                'balance' => (float) $row->balance,
                'percent' => $principal > 0 ? ((float) $row->balance / $principal) * 100 : 0,
            ])->sortByDesc('balance')->values()->all();

            $positiveMonths = collect($contributionTrend)->filter(fn($m) => $m['net_contributed'] > 0);
            $monthlyContribution = $positiveMonths->count() > 0 ? (float) $positiveMonths->avg('net_contributed') : 0.0;

            $projections = [];
            foreach ([5, 10, 20] as $years) {
                $projections[] = [
                    'years' => $years,
                    'projected_value' => $this->compoundGrowth($principal, $monthlyContribution, $years),
                ];
            }

            $annualExpenses = 0.0;
            for ($i = 0; $i < 12; $i++) {
                $cursor = now()->subMonths($i);
                $annualExpenses += (float) ExpenseCalculation::where('types', 'EXPENSE')
                    ->whereYear('date', $cursor->year)->whereMonth('date', $cursor->month)
                    ->sum('amount');
            }

            $targetAmount = $annualExpenses > 0 ? $annualExpenses / 0.04 : 0.0;
            $etaYears = null;
            if ($targetAmount > 0) {
                if ($principal >= $targetAmount) {
                    $etaYears = 0;
                } else {
                    for ($years = 1; $years <= 40; $years++) {
                        if ($this->compoundGrowth($principal, $monthlyContribution, $years) >= $targetAmount) {
                            $etaYears = $years;
                            break;
                        }
                    }
                }
            }

            return [
                'contribution_trend' => $contributionTrend,
                'asset_allocation' => $assetAllocation,
                'total_invested' => $principal,
                'monthly_contribution' => $monthlyContribution,
                'compound_growth_projection' => [
                    'assumed_annual_return_rate' => self::ASSUMED_ANNUAL_RETURN_RATE,
                    'assumptions' => 'No market-valuation data source exists in this app; projections assume a flat ' . (self::ASSUMED_ANNUAL_RETURN_RATE * 100) . '% annual return compounded monthly on current holdings plus the average recent monthly contribution.',
                    'projections' => $projections,
                ],
                'four_percent_rule' => [
                    'target_amount' => $targetAmount,
                    'current_amount' => $principal,
                    'progress_percent' => $targetAmount > 0 ? ($principal / $targetAmount) * 100 : 0,
                    'eta_years' => $etaYears,
                ],
            ];
        });
    }

    public function getDayOfWeekSpending(Carbon $start, Carbon $end): array
    {
        return PatternAnalyzer::dayOfWeekSpending($start, $end);
    }

    public function getMonthlyComparison(int $year, int $month): array
    {
        $current = Carbon::create($year, $month, 1);
        $previous = $current->copy()->subMonthNoOverflow();

        $currentTotals = $this->monthTotals($current->year, $current->month);
        $previousTotals = $this->monthTotals($previous->year, $previous->month);

        $pctChange = fn(float $curr, float $prev): ?float => $prev > 0 ? (($curr - $prev) / $prev) * 100 : null;

        return [
            'current' => array_merge(['label' => $current->format('M Y')], $currentTotals),
            'previous' => array_merge(['label' => $previous->format('M Y')], $previousTotals),
            'income_change_percent' => $pctChange($currentTotals['income'], $previousTotals['income']),
            'expense_change_percent' => $pctChange($currentTotals['expense'], $previousTotals['expense']),
            'net_change_percent' => $pctChange($currentTotals['net'], $previousTotals['net']),
        ];
    }

    /**
     * Standardized weights (25/20/20/20/15 — chosen because it sums to 100%,
     * the spec had two conflicting weight sets): Savings Rate, Budget
     * Adherence, Investment Growth, Expense Management, Cash Runway. Every
     * raw input comes from FinancialAnalyticsService — no new raw queries.
     */
    public function calculateFinancialHealthScore(): array
    {
        $now = now();
        $key = 'financial_analysis:health_score:' . $now->year . ':' . $now->month;

        return Cache::remember($key, now()->addMinutes(self::TTL_MINUTES), function () use ($now) {
            $year = (int) $now->year;
            $month = (int) $now->month;

            $savingsRate = $this->analytics->savingsRateTrend(3)['average'];
            $savingsScore = $this->clamp($savingsRate / 20 * 100, 0, 100);

            $budgetRows = $this->analytics->budgetUtilization($year, $month);
            $insufficientBudgetData = empty($budgetRows);
            if ($insufficientBudgetData) {
                $budgetScore = 50.0;
            } else {
                $rowScores = array_map(function ($row) {
                    $utilization = $row['utilization_percent'];
                    return $utilization <= 100 ? 100.0 : max(100.0 - ($utilization - 100), 0.0);
                }, $budgetRows);
                $budgetScore = array_sum($rowScores) / count($rowScores);
            }

            $contributionTrend = $this->analytics->investmentContributionTrend(6);
            $positiveMonths = count(array_filter($contributionTrend, fn($m) => $m['net_contributed'] > 0));
            $investmentScore = $this->clamp($positiveMonths / 6 * 100, 0, 100);

            $anomalies = $this->analytics->anomalies($year, $month);
            $expenseScore = 100.0;
            foreach ($anomalies as $anomaly) {
                $expenseScore -= min($anomaly['over_percent'] / 2, 15);
            }
            $expenseScore = max($expenseScore, 0.0);

            $runway = $this->analytics->cashRunway();
            $runwayScore = is_infinite($runway) ? 100.0 : $this->clamp($runway / 90 * 100, 0, 100);

            $components = [
                'savings_rate' => ['weight' => 25, 'score' => (int) round($savingsScore), 'raw_value' => round($savingsRate, 2)],
                'budget_adherence' => ['weight' => 20, 'score' => (int) round($budgetScore), 'raw_value' => round($budgetScore, 2), 'insufficient_data' => $insufficientBudgetData],
                'investment_growth' => ['weight' => 20, 'score' => (int) round($investmentScore), 'raw_value' => $positiveMonths],
                'expense_management' => ['weight' => 20, 'score' => (int) round($expenseScore), 'raw_value' => count($anomalies)],
                'cash_runway' => ['weight' => 15, 'score' => (int) round($runwayScore), 'raw_value' => is_infinite($runway) ? null : round($runway, 1)],
            ];

            $totalScore = ($savingsScore * 0.25) + ($budgetScore * 0.20) + ($investmentScore * 0.20) + ($expenseScore * 0.20) + ($runwayScore * 0.15);
            $totalScore = (int) round($this->clamp($totalScore, 0, 100));

            return [
                'score' => $totalScore,
                'label' => $this->healthLabel($totalScore),
                'components' => $components,
                'generated_at' => now()->toDateTimeString(),
            ];
        });
    }

    /** Call after any ExpenseCalculation/HandCash/ProjectedExpense write, alongside FinancialAnalyticsService::forgetAll(). */
    public static function forgetAll(): void
    {
        $now = now();
        $filtersHash = md5(json_encode(['period' => 'this_month']));
        $monthStart = $now->copy()->startOfMonth()->format('Ymd');
        $monthEnd = $now->copy()->endOfMonth()->format('Ymd');

        Cache::forget('financial_analysis:dashboard:' . $filtersHash);
        Cache::forget('financial_analysis:health_score:' . $now->year . ':' . $now->month);
        Cache::forget('financial_analysis:investment_widget:' . $now->format('Ymd'));
        Cache::forget('financial_analysis:ai_insights:' . $monthStart . '-' . $monthEnd . ':all');

        foreach (['balance', 'projected_balance', 'month_income', 'month_expense', 'month_net', 'savings_rate', 'burn_rate', 'runway', 'health_score'] as $type) {
            Cache::forget('financial_analysis:kpi:' . $type . ':' . $filtersHash);
        }
    }

    private function buildKpiSet(Carbon $start, Carbon $end): array
    {
        $income = $this->sumByType('INCOME', $start, $end);
        $expense = $this->sumByType('EXPENSE', $start, $end);
        $runway = $this->analytics->cashRunway();

        $investmentSave = (float) HandCash::where('rules', 'INVESTMENT')->where('types', 'SAVE')->sum('amount');
        $investmentWithdraw = (float) HandCash::where('rules', 'INVESTMENT')->where('types', 'WIDROWS')->sum('amount');

        return [
            'balance' => $this->analytics->currentCashBalance(),
            'projected_balance' => $this->analytics->projectedMonthEndBalance(),
            'period_income' => $income,
            'period_expense' => $expense,
            'period_net' => $income - $expense,
            'savings_rate' => $income > 0 ? (($income - $expense) / $income) * 100 : 0,
            'burn_rate' => $this->analytics->burnRate($start, $end),
            'runway_days' => is_infinite($runway) ? null : (int) $runway,
            'total_investments' => $investmentSave - $investmentWithdraw,
        ];
    }

    private function monthTotals(int $year, int $month): array
    {
        $income = $this->sumByType('INCOME', Carbon::create($year, $month, 1)->startOfMonth(), Carbon::create($year, $month, 1)->endOfMonth());
        $expense = $this->sumByType('EXPENSE', Carbon::create($year, $month, 1)->startOfMonth(), Carbon::create($year, $month, 1)->endOfMonth());

        return ['income' => $income, 'expense' => $expense, 'net' => $income - $expense];
    }

    private function sumByType(string $type, Carbon $start, Carbon $end): float
    {
        return (float) ExpenseCalculation::where('types', $type)
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->sum('amount');
    }

    private function compoundGrowth(float $principal, float $monthlyContribution, int $years): float
    {
        $n = $years * 12;
        $r = self::ASSUMED_ANNUAL_RETURN_RATE / 12;

        if ($r <= 0) {
            return $principal + $monthlyContribution * $n;
        }

        return $principal * ((1 + $r) ** $n) + $monthlyContribution * (((1 + $r) ** $n - 1) / $r);
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    private function healthLabel(int $score): string
    {
        return match (true) {
            $score >= 80 => 'Excellent',
            $score >= 60 => 'Good',
            $score >= 40 => 'Fair',
            $score >= 20 => 'Needs Attention',
            default => 'Critical',
        };
    }

    private function normalizeFilters(array $filters): array
    {
        ksort($filters);
        return $filters;
    }
}
