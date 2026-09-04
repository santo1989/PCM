<?php

namespace App\Services;

use App\Models\Category;
use App\Models\ExpenseCalculation;
use App\Models\HandCash;
use App\Models\ProjectedExpense;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Owns every "financial intelligence" number in the app (burn rate, runway,
 * budget utilization, anomaly detection, forecasts, ...). Report controllers
 * call into this rather than re-deriving similar aggregates in each one.
 *
 * Every public method is cached (file cache, no Redis needed) with a TTL and
 * key scoped to its parameters, matching the pattern already used by
 * HandCashController's interactiveDashboard* endpoints. Call forgetAll() (or
 * the narrower forget*() helpers) after writes; the ExpenseCalculation/
 * HandCash controllers already do this for the dashboard cache keys, so the
 * same call sites just grow to include these too.
 */
class FinancialAnalyticsService
{
    private const TTL_MINUTES = 10;

    /**
     * Net balance across every HandCash account: total SAVE minus total WIDROWS.
     * Deliberately simpler than the nuanced per-rule "$hands" figure computed in
     * HandCashController::index() (which makes business-specific calls about which
     * rules count as cash vs. loan vs. asset) — this is a plain net-across-everything
     * number for KPI/insight purposes, not a replacement for that page's balance sheet.
     */
    public function currentCashBalance(): float
    {
        return Cache::remember('analytics:balance', now()->addMinutes(self::TTL_MINUTES), function () {
            $save = (float) HandCash::where('types', 'SAVE')->sum('amount');
            $withdraw = (float) HandCash::where('types', 'WIDROWS')->sum('amount');
            return $save - $withdraw;
        });
    }

    /** Average daily expense over a period. */
    public function burnRate(Carbon $start, Carbon $end): float
    {
        $key = "analytics:burn_rate:{$start->format('Ymd')}:{$end->format('Ymd')}";

        return Cache::remember($key, now()->addMinutes(self::TTL_MINUTES), function () use ($start, $end) {
            $days = max($start->diffInDays($end) + 1, 1);
            $total = (float) ExpenseCalculation::where('types', 'EXPENSE')
                ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->sum('amount');

            return $total / $days;
        });
    }

    /**
     * Current balance projected to the end of the current calendar month, by
     * extrapolating this month's income/expense pace over the remaining days.
     */
    public function projectedMonthEndBalance(): float
    {
        return Cache::remember('analytics:projected_month_end:' . now()->format('Ym'), now()->addMinutes(self::TTL_MINUTES), function () {
            $start = now()->startOfMonth();
            $today = now();
            $daysElapsed = max($start->diffInDays($today) + 1, 1);
            $daysInMonth = now()->daysInMonth;

            $incomeSoFar = (float) ExpenseCalculation::where('types', 'INCOME')
                ->whereBetween('date', [$start->format('Y-m-d'), $today->format('Y-m-d')])
                ->sum('amount');
            $expenseSoFar = (float) ExpenseCalculation::where('types', 'EXPENSE')
                ->whereBetween('date', [$start->format('Y-m-d'), $today->format('Y-m-d')])
                ->sum('amount');

            $dailyIncomePace = $incomeSoFar / $daysElapsed;
            $dailyExpensePace = $expenseSoFar / $daysElapsed;
            $remainingDays = max($daysInMonth - $daysElapsed, 0);

            $projectedNetForRest = ($dailyIncomePace - $dailyExpensePace) * $remainingDays;

            return $this->currentCashBalance() + $projectedNetForRest;
        });
    }

    /**
     * Actual spend vs. that month's ProjectedExpense rows, per category.
     * Returns [['category_id', 'category_name', 'projected', 'actual', 'utilization_percent'], ...].
     */
    public function budgetUtilization(int $year, int $month): array
    {
        $key = "analytics:budget_utilization:{$year}:{$month}";

        return Cache::remember($key, now()->addMinutes(self::TTL_MINUTES), function () use ($year, $month) {
            $projected = ProjectedExpense::whereYear('date', $year)
                ->whereMonth('date', $month)
                ->get()
                ->keyBy('category_id');

            if ($projected->isEmpty()) {
                return [];
            }

            $actual = ExpenseCalculation::where('types', 'EXPENSE')
                ->whereYear('date', $year)
                ->whereMonth('date', $month)
                ->groupBy('category_id')
                ->select('category_id', DB::raw('SUM(amount) as total'))
                ->pluck('total', 'category_id');

            $categoryNames = Category::whereIn('id', $projected->keys())->pluck('name', 'id');

            return $projected->map(function ($row) use ($actual, $categoryNames) {
                $projectedAmount = (float) $row->amount;
                $actualAmount = (float) ($actual[$row->category_id] ?? 0);

                return [
                    'category_id' => $row->category_id,
                    'category_name' => $categoryNames[$row->category_id] ?? 'Unknown',
                    'projected' => $projectedAmount,
                    'actual' => $actualAmount,
                    'utilization_percent' => $projectedAmount > 0 ? ($actualAmount / $projectedAmount) * 100 : 0,
                ];
            })->sortByDesc('utilization_percent')->values()->all();
        });
    }

    /** Rolling N-month average savings rate ((income - expense) / income), most recent N full months. */
    public function savingsRateTrend(int $months = 3): array
    {
        $key = "analytics:savings_trend:{$months}:" . now()->format('Ym');

        return Cache::remember($key, now()->addMinutes(self::TTL_MINUTES), function () use ($months) {
            $rates = [];

            for ($i = $months - 1; $i >= 0; $i--) {
                $cursor = now()->subMonths($i);
                $income = (float) ExpenseCalculation::where('types', 'INCOME')
                    ->whereYear('date', $cursor->year)->whereMonth('date', $cursor->month)
                    ->sum('amount');
                $expense = (float) ExpenseCalculation::where('types', 'EXPENSE')
                    ->whereYear('date', $cursor->year)->whereMonth('date', $cursor->month)
                    ->sum('amount');

                $rates[] = [
                    'label' => $cursor->format('M Y'),
                    'rate' => $income > 0 ? (($income - $expense) / $income) * 100 : 0,
                ];
            }

            return [
                'months' => $rates,
                'average' => count($rates) > 0 ? array_sum(array_column($rates, 'rate')) / count($rates) : 0,
            ];
        });
    }

    /**
     * Categories whose current-month spend exceeds the 90th percentile of that
     * category's own historical monthly spend (percentile computed in PHP over
     * the collection of past monthly sums — portable across MySQL versions).
     */
    public function anomalies(int $year, int $month): array
    {
        $key = "analytics:anomalies:{$year}:{$month}";

        return Cache::remember($key, now()->addMinutes(self::TTL_MINUTES), function () use ($year, $month) {
            $currentMonthStart = Carbon::create($year, $month, 1);

            $currentSpend = ExpenseCalculation::where('types', 'EXPENSE')
                ->whereYear('date', $year)->whereMonth('date', $month)
                ->groupBy('category_id')
                ->select('category_id', DB::raw('SUM(amount) as total'))
                ->pluck('total', 'category_id');

            if ($currentSpend->isEmpty()) {
                return [];
            }

            $categoryNames = Category::whereIn('id', $currentSpend->keys())->pluck('name', 'id');
            $flagged = [];

            foreach ($currentSpend as $categoryId => $amount) {
                $history = ExpenseCalculation::where('types', 'EXPENSE')
                    ->where('category_id', $categoryId)
                    ->where('date', '<', $currentMonthStart->format('Y-m-d'))
                    ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
                    ->select(DB::raw('SUM(amount) as total'))
                    ->pluck('total')
                    ->map(fn($v) => (float) $v)
                    ->sort()
                    ->values();

                // Need a meaningful amount of history before "90th percentile" means anything.
                if ($history->count() < 4) {
                    continue;
                }

                $p90 = $this->percentile($history->all(), 90);

                if ((float) $amount > $p90 && $p90 > 0) {
                    $flagged[] = [
                        'category_id' => $categoryId,
                        'category_name' => $categoryNames[$categoryId] ?? 'Unknown',
                        'current' => (float) $amount,
                        'p90_historical' => $p90,
                        'over_percent' => (($amount - $p90) / $p90) * 100,
                        'potential_saving' => (float) $amount - $this->median($history->all()),
                    ];
                }
            }

            usort($flagged, fn($a, $b) => $b['potential_saving'] <=> $a['potential_saving']);

            return $flagged;
        });
    }

    /**
     * Live, pace-based spending comparison for the current (possibly still
     * in-progress) month — unlike anomalies(), this is meaningful from day 1
     * of the month rather than only near month-end, and needs only 1 prior
     * month of history (not 4+) to produce a result. Compares this month's
     * average-per-day spend so far against each category's historical
     * average-per-day spend, so a mostly-empty in-progress month is compared
     * fairly against full historical months rather than against their totals.
     */
    public function livePace(int $year, int $month, int $historyMonths = 6): array
    {
        $key = "analytics:live_pace:{$year}:{$month}:{$historyMonths}:" . now()->format('YmdH');

        return Cache::remember($key, now()->addMinutes(5), function () use ($year, $month, $historyMonths) {
            $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
            $isCurrentMonth = $periodStart->isSameMonth(now());
            $daysElapsed = $isCurrentMonth ? now()->day : $periodStart->daysInMonth;

            $currentSpend = ExpenseCalculation::where('types', 'EXPENSE')
                ->whereYear('date', $year)->whereMonth('date', $month)
                ->groupBy('category_id')
                ->select('category_id', DB::raw('SUM(amount) as total'))
                ->pluck('total', 'category_id');

            if ($currentSpend->isEmpty()) {
                return [];
            }

            $categoryNames = Category::whereIn('id', $currentSpend->keys())->pluck('name', 'id');
            $rows = [];

            foreach ($currentSpend as $categoryId => $amount) {
                $currentPace = $daysElapsed > 0 ? (float) $amount / $daysElapsed : 0;

                // Historical daily pace: average of (that month's total / days in that month)
                // over up to $historyMonths prior fully-completed months that have any spend.
                $historicalDailyPaces = [];
                for ($i = 1; $i <= $historyMonths; $i++) {
                    $cursor = $periodStart->copy()->subMonths($i);
                    $monthTotal = (float) ExpenseCalculation::where('types', 'EXPENSE')
                        ->where('category_id', $categoryId)
                        ->whereYear('date', $cursor->year)->whereMonth('date', $cursor->month)
                        ->sum('amount');

                    if ($monthTotal > 0) {
                        $historicalDailyPaces[] = $monthTotal / $cursor->daysInMonth;
                    }
                }

                if (empty($historicalDailyPaces)) {
                    continue; // no history at all yet for this category — nothing to compare against
                }

                $historicalDailyAvg = array_sum($historicalDailyPaces) / count($historicalDailyPaces);
                $variancePercent = $historicalDailyAvg > 0
                    ? (($currentPace - $historicalDailyAvg) / $historicalDailyAvg) * 100
                    : 0;

                $rows[] = [
                    'category_id' => $categoryId,
                    'category_name' => $categoryNames[$categoryId] ?? 'Unknown',
                    'month_to_date' => (float) $amount,
                    'days_elapsed' => $daysElapsed,
                    'current_daily_pace' => $currentPace,
                    'historical_daily_pace' => $historicalDailyAvg,
                    'variance_percent' => $variancePercent,
                    'projected_month_total' => $currentPace * $periodStart->daysInMonth,
                    'history_months_used' => count($historicalDailyPaces),
                ];
            }

            usort($rows, fn($a, $b) => $b['variance_percent'] <=> $a['variance_percent']);

            return $rows;
        });
    }

    /** Days of runway: current balance / average daily expense over the last 90 days. */
    public function cashRunway(): float
    {
        return Cache::remember('analytics:runway', now()->addMinutes(self::TTL_MINUTES), function () {
            $dailyExpense = $this->burnRate(now()->subDays(90), now());
            if ($dailyExpense <= 0) {
                return INF;
            }

            return max($this->currentCashBalance(), 0) / $dailyExpense;
        });
    }

    /**
     * Net contributed to INVESTMENT-rule HandCash accounts, month by month.
     * Honest substitute for "ROI": this app has no market-valuation data source,
     * only contribution/withdrawal transactions, so there is no way to compute a
     * true return percentage. This is cumulative net contribution over time.
     */
    public function investmentContributionTrend(int $months = 12): array
    {
        $key = "analytics:investment_trend:{$months}:" . now()->format('Ym');

        return Cache::remember($key, now()->addMinutes(self::TTL_MINUTES), function () use ($months) {
            $series = [];
            $cumulative = 0.0;

            for ($i = $months - 1; $i >= 0; $i--) {
                $cursor = now()->subMonths($i);
                $save = (float) HandCash::where('rules', 'INVESTMENT')->where('types', 'SAVE')
                    ->whereYear('date', $cursor->year)->whereMonth('date', $cursor->month)
                    ->sum('amount');
                $withdraw = (float) HandCash::where('rules', 'INVESTMENT')->where('types', 'WIDROWS')
                    ->whereYear('date', $cursor->year)->whereMonth('date', $cursor->month)
                    ->sum('amount');

                $cumulative += $save - $withdraw;
                $series[] = ['label' => $cursor->format('M Y'), 'net_contributed' => $save - $withdraw, 'cumulative' => $cumulative];
            }

            return $series;
        });
    }

    /**
     * Least-squares linear regression over (index, value) pairs, plus a naive
     * confidence band from the residual standard error. No ML library needed
     * for single-variable regression.
     *
     * @param float[] $values Historical values in chronological order (index = position).
     * @return array{forecast: float[], upper: float[], lower: float[], slope: float, intercept: float}
     */
    public function linearForecast(array $values, int $periodsAhead): array
    {
        $n = count($values);
        if ($n < 2) {
            $flat = $n === 1 ? $values[0] : 0.0;
            return [
                'forecast' => array_fill(0, $periodsAhead, $flat),
                'upper' => array_fill(0, $periodsAhead, $flat),
                'lower' => array_fill(0, $periodsAhead, $flat),
                'slope' => 0.0,
                'intercept' => $flat,
            ];
        }

        $xs = range(0, $n - 1);
        $meanX = array_sum($xs) / $n;
        $meanY = array_sum($values) / $n;

        $numerator = 0.0;
        $denominator = 0.0;
        foreach ($xs as $i => $x) {
            $numerator += ($x - $meanX) * ($values[$i] - $meanY);
            $denominator += ($x - $meanX) ** 2;
        }

        $slope = $denominator > 0 ? $numerator / $denominator : 0.0;
        $intercept = $meanY - $slope * $meanX;

        // Residual standard error, for a simple +/- band (not a rigorous prediction interval).
        $sumSquaredResiduals = 0.0;
        foreach ($xs as $i => $x) {
            $predicted = $slope * $x + $intercept;
            $sumSquaredResiduals += ($values[$i] - $predicted) ** 2;
        }
        $stdError = $n > 2 ? sqrt($sumSquaredResiduals / ($n - 2)) : 0.0;

        $forecast = [];
        $upper = [];
        $lower = [];
        for ($step = 1; $step <= $periodsAhead; $step++) {
            $x = $n - 1 + $step;
            $y = $slope * $x + $intercept;
            $forecast[] = $y;
            $upper[] = $y + $stdError;
            $lower[] = max($y - $stdError, 0);
        }

        return compact('forecast', 'upper', 'lower', 'slope', 'intercept');
    }

    private function percentile(array $sortedValues, float $percentile): float
    {
        $count = count($sortedValues);
        if ($count === 0) {
            return 0.0;
        }
        if ($count === 1) {
            return $sortedValues[0];
        }

        $index = ($percentile / 100) * ($count - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);

        if ($lower === $upper) {
            return $sortedValues[$lower];
        }

        $fraction = $index - $lower;
        return $sortedValues[$lower] + $fraction * ($sortedValues[$upper] - $sortedValues[$lower]);
    }

    private function median(array $values): float
    {
        sort($values);
        return $this->percentile($values, 50);
    }

    /** Call after any ExpenseCalculation/HandCash/ProjectedExpense write that could change these figures. */
    public static function forgetAll(): void
    {
        $now = now();
        Cache::forget('analytics:balance');
        Cache::forget('analytics:runway');
        Cache::forget('analytics:projected_month_end:' . $now->format('Ym'));
        Cache::forget('analytics:budget_utilization:' . $now->year . ':' . $now->month);
        Cache::forget('analytics:anomalies:' . $now->year . ':' . $now->month);
        Cache::forget("analytics:live_pace:{$now->year}:{$now->month}:6:" . $now->format('YmdH'));
        foreach ([1, 3, 6, 12] as $months) {
            Cache::forget("analytics:savings_trend:{$months}:" . $now->format('Ym'));
            Cache::forget("analytics:investment_trend:{$months}:" . $now->format('Ym'));
        }
    }
}
