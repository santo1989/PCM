<?php

namespace App\Services;

use App\Models\Category;
use App\Models\ExpenseCalculation;
use App\Models\HandCash;
use App\Models\ProjectedExpense;
use App\Services\Analytics\TimeSeries;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Data-driven monthly budget for the Budget Projection page, the month-wise
 * "budget vs actual" review and the budget analytics card.
 *
 * Every monthly series (each expense category, salary, DPS/FD installments,
 * total spend) is forecast by TimeSeries::ensemble(): six models (seasonal
 * naive, WMA, SES, damped Holt, Holt-Winters, Croston/SBA) are back-tested on
 * the series' own last 6 months and blended by accuracy, with an 80% interval
 * from the back-test errors. Only data before the target month is used, so
 * past months are honest back-tests.
 *
 * Budget rule (config finance.income_allocation): 70% of salary covers
 * expenses plus FD/DPS installments up to their 14%-of-salary share;
 * installments above that count toward the separate 20% savings bucket.
 * Installments are hand-cash transfers, so the cap for expense categories is
 * 70% of salary minus the counted installments. Over the cap, WANTS are cut
 * first (up to 60%), then other (40%), then NEEDS (20%); SAVINGS-rule lines
 * are never cut. What still doesn't fit is reported as the structural gap.
 */
class BudgetPredictor
{
    public const CAP = 0.70;

    /** Max share of each rule's forecast the cap may cut, in this order. */
    private const CUT_ORDER = ['WANTS' => 0.60, 'OTHER' => 0.40, 'NEEDS' => 0.20];

    /** Hand-cash accounts that hold monthly installments (part of the 70%). */
    public const INSTALLMENT_ACCOUNTS = ['DPS', 'ISLAMIC_DPS', 'FD'];
    public const LIQUID_ACCOUNTS = ['CASH', 'PETI', 'CITY_BANK', 'CITY_BANK_ISLAMIC', 'SONALI_BANK_GULSHAN',
        'SONALI_BANK_TONGI', 'DBBL', 'PBL', 'MOBILE_BKASH', 'MOBILE_ROCKET', 'MOBILE_NAGAD'];

    /** @var array<int, array<string, float>> [category_id][Y-m] => total */
    private array $totals = [];
    /** @var array<string, float> [Y-m] => total, for every expense category */
    private array $spend = [];
    private array $salary = [];
    private array $installments = [];
    private array $invested = [];
    private array $balances = [];
    private ?Carbon $first = null;
    private $categories;
    private array $memo = [];

    public function __construct()
    {
        $this->categories = Category::where('types', 'EXPENSE')->get(['id', 'name', 'rules'])->keyBy('id');
        $amount = "SUM(REPLACE(amount, ',', ''))";
        $ym = "DATE_FORMAT(date, '%Y-%m')";

        foreach (ExpenseCalculation::where('types', 'EXPENSE')->groupBy('category_id', 'ym')
            ->selectRaw("category_id, $ym as ym, $amount as total")->get() as $r) {
            $this->totals[$r->category_id][$r->ym] = (float) $r->total;
            $this->spend[$r->ym] = ($this->spend[$r->ym] ?? 0) + (float) $r->total;
        }

        $this->salary = $this->monthly(ExpenseCalculation::where('types', 'INCOME')->where('category_id', 1), $amount, $ym);
        // Installments linked to an expense row are already in the expense totals.
        $this->installments = $this->monthly(HandCash::whereIn('rules', self::INSTALLMENT_ACCOUNTS)
            ->where('types', 'SAVE')->whereNull('expense_calculation_id'), $amount, $ym);
        $this->invested = $this->monthly(HandCash::where('rules', 'INVESTMENT'),
            "SUM(CASE WHEN types = 'SAVE' THEN REPLACE(amount, ',', '') ELSE -REPLACE(amount, ',', '') END)", $ym);
        $this->balances = HandCash::groupBy('rules')
            ->selectRaw("rules, SUM(CASE WHEN types = 'SAVE' THEN REPLACE(amount, ',', '') ELSE -REPLACE(amount, ',', '') END) as balance")
            ->pluck('balance', 'rules')->map(fn($v) => (float) $v)->all();

        $first = ExpenseCalculation::min('date');
        $this->first = $first ? Carbon::parse($first)->startOfMonth() : null;
    }

    private function monthly($query, string $amount, string $ym): array
    {
        return $query->groupBy('ym')->selectRaw("$ym as ym, $amount as total")
            ->pluck('total', 'ym')->map(fn($v) => (float) $v)->all();
    }

    /**
     * History columns of the Budget Projection table (page and Excel), per category id:
     * avg  = total spend / months covered (zero-spend months count). All time = first
     *        data month to the last completed month; a range = its length in months.
     * last = last calendar month's spend.
     * saved = this month's saved projected budget (null when none was saved).
     */
    public static function historyColumns(?string $start = null, ?string $end = null): array
    {
        $amount = "SUM(REPLACE(amount, ',', ''))";
        $byCategory = fn($from, $to) => ExpenseCalculation::where('types', 'EXPENSE')
            ->whereBetween('date', [$from, $to])->groupBy('category_id')
            ->selectRaw("category_id, $amount as total")->pluck('total', 'category_id')->map(fn($v) => (float) $v);

        if ($start && $end) {
            $from = Carbon::parse($start)->startOfDay();
            $to = Carbon::parse($end)->endOfDay();
            // Average days per month (365.25 / 12), so partial-month ranges are averaged fairly.
            $months = max(1, round(($from->diffInDays($to) + 1) / 30.4375, 1));
            $label = $from->toDateString() . ' to ' . $to->toDateString();
        } else {
            $first = ExpenseCalculation::where('types', 'EXPENSE')->min('date');
            $from = $first ? Carbon::parse($first)->startOfMonth() : now()->startOfMonth();
            $to = now()->subMonthNoOverflow()->endOfMonth();
            $months = max(1, $from->diffInMonths($to->copy()->addDay()));
            $label = 'All Time: ' . $from->format('M Y') . ' – ' . $to->format('M Y');
        }

        $prev = now()->subMonthNoOverflow();
        $saved = ProjectedExpense::whereYear('date', now()->year)->whereMonth('date', now()->month)
            ->get(['category_id', 'amount'])
            ->groupBy('category_id')
            ->map(fn($rows) => (float) $rows->sum(fn($r) => (float) str_replace(',', '', $r->amount)));

        return [
            'avg' => $byCategory($from->toDateString(), $to->toDateString())->map(fn($v) => round($v / $months, 2))->all(),
            'months' => $months,
            'label' => $label,
            'last' => $byCategory($prev->copy()->startOfMonth()->toDateString(), $prev->copy()->endOfMonth()->toDateString())->all(),
            'last_label' => $prev->format('F Y'),
            'saved' => $saved->all(),
            'saved_label' => now()->format('F Y'),
        ];
    }

    /** Cache key part that changes whenever expense or hand-cash data changes. */
    public static function signature(): string
    {
        return md5(json_encode([
            ExpenseCalculation::count(), ExpenseCalculation::max('updated_at'),
            HandCash::count(), HandCash::max('updated_at'), ProjectedExpense::max('updated_at'),
            Category::max('updated_at'),
        ]));
    }

    public static function cached(string $what, Carbon $month, callable $build): array
    {
        return Cache::remember("budget_predictor:$what:" . $month->format('Y-m') . ':' . self::signature(), now()->addHour(), $build);
    }

    /** Months from the first data month up to (not including) $month. */
    private function series(array $byYm, Carbon $month): array
    {
        $out = [];
        if (!$this->first) {
            return $out;
        }
        for ($m = $this->first->copy(); $m->lt($month->copy()->startOfMonth()); $m->addMonthNoOverflow()) {
            $out[] = $byYm[$m->format('Y-m')] ?? 0.0;
        }

        return $out;
    }

    private function model(string $key, array $byYm, Carbon $month): array
    {
        return $this->memo[$key . '@' . $month->format('Y-m')] ??= TimeSeries::ensemble($this->series($byYm, $month));
    }

    public function categoryModel(int $cat, Carbon $month): array
    {
        return $this->model("cat$cat", $this->totals[$cat] ?? [], $month);
    }

    private function actual(int $cat, Carbon $month): float
    {
        return $this->totals[$cat][$month->format('Y-m')] ?? 0.0;
    }

    /**
     * Salary for the month; when not received yet, the forecast, but never more than
     * the median of the last 3 salaries (prudent: bonuses shouldn't raise the budget).
     */
    public function income(Carbon $month): array
    {
        $got = $this->salary[$month->format('Y-m')] ?? 0;
        if ($got > 0) {
            return [$got, 'actual'];
        }
        $recent = array_slice(array_filter($this->series($this->salary, $month)), -3);
        $forecast = $this->model('salary', $this->salary, $month)['forecast'];

        return [round($recent ? min($forecast, TimeSeries::median($recent)) : $forecast), 'forecast'];
    }

    private function rule(int $cat): string
    {
        $rule = $this->categories[$cat]->rules ?? null;

        return in_array($rule, ['NEEDS', 'WANTS', 'SAVINGS'], true) ? $rule : 'OTHER';
    }

    /**
     * Budget for $month: [month, income, income_source, limit, installments, spend_cap,
     * rows, total_forecast, total_budget, fits]. rows: category_id => [category_id, name,
     * rule, forecast, lo, hi, method, budget].
     */
    public function predict(Carbon $month, float $reduction = 0.0): array
    {
        $month = $month->copy()->startOfMonth();
        [$income, $source] = $this->income($month);
        $limit = round($income * self::CAP, 2);
        $installments = round($this->model('installments', $this->installments, $month)['forecast']);
        $counted = $this->countedInstallments($installments, $income);
        $cap = max(0, $limit - $counted);
        $reduction = max(0, min(0.9, $reduction));

        $rows = [];
        foreach ($this->categories as $id => $category) {
            $m = $this->categoryModel($id, $month);
            $f = round($m['forecast']);
            if ($f <= 0) {
                continue;
            }
            $rule = $this->rule($id);
            $rows[$id] = [
                'category_id' => $id,
                'name' => $category->name,
                'rule' => $rule,
                'forecast' => $f,
                'lo' => round($m['lo']),
                'hi' => round($m['hi']),
                'method' => $m['method'],
                'budget' => $rule === 'SAVINGS' ? $f : round($f * (1 - $reduction)),
            ];
        }

        // Cut to the cap, cheapest-to-cut rules first.
        $excess = array_sum(array_column($rows, 'budget')) - $cap;
        foreach (self::CUT_ORDER as $rule => $maxShare) {
            if ($excess <= 0 || $income <= 0) {
                break;
            }
            $room = [];
            foreach ($rows as $id => $r) {
                if ($r['rule'] === $rule) {
                    $room[$id] = max(0, $r['budget'] - $r['forecast'] * (1 - $maxShare));
                }
            }
            $available = array_sum($room);
            if ($available <= 0) {
                continue;
            }
            $take = min($excess, $available);
            foreach ($room as $id => $r) {
                $rows[$id]['budget'] = round($rows[$id]['budget'] - $take * $r / $available);
            }
            $excess -= $take;
        }
        uasort($rows, fn($a, $b) => $b['budget'] <=> $a['budget']);
        $totalBudget = array_sum(array_column($rows, 'budget'));

        return [
            'month' => $month->format('Y-m'),
            'income' => $income,
            'income_source' => $source,
            'limit' => $limit,
            'installments' => $installments,
            'installments_counted' => $counted,
            'spend_cap' => $cap,
            'rows' => $rows,
            'total_forecast' => array_sum(array_column($rows, 'forecast')),
            'total_budget' => $totalBudget,
            'fits' => $income > 0 && $totalBudget <= $cap + 1,
            'gap' => max(0, $totalBudget - $cap),
        ];
    }

    /** Installments count toward the 70% only up to their share (14% of salary). */
    private function countedInstallments(float $installments, float $income): float
    {
        return round(min($installments, $income * config('finance.income_allocation.installments')));
    }

    /**
     * Budget vs actual for one month. Budget is the saved projection when there is
     * one, otherwise what the model would have predicted before the month began.
     */
    public function compare(Carbon $month): array
    {
        $month = $month->copy()->startOfMonth();
        $plan = $this->predict($month);
        $saved = ProjectedExpense::whereYear('date', $month->year)->whereMonth('date', $month->month)
            ->get()->pluck('amount', 'category_id')->map(fn($v) => (float) str_replace(',', '', $v));
        $source = $saved->isNotEmpty() ? 'saved' : 'model';

        $ids = array_unique(array_merge(
            array_keys($plan['rows']),
            $saved->keys()->all(),
            $this->categories->keys()->filter(fn($id) => $this->actual($id, $month) > 0)->all()
        ));

        $rows = [];
        foreach ($ids as $id) {
            if (!isset($this->categories[$id])) {
                continue;
            }
            $budget = $source === 'saved' ? (float) ($saved[$id] ?? 0) : (float) ($plan['rows'][$id]['budget'] ?? 0);
            $actual = $this->actual($id, $month);
            $rows[] = [
                'category_id' => $id,
                'name' => $this->categories[$id]->name,
                'rule' => $this->rule($id),
                'budget' => $budget,
                'actual' => $actual,
                'expected_lo' => $plan['rows'][$id]['lo'] ?? 0,
                'expected_hi' => $plan['rows'][$id]['hi'] ?? 0,
                'gap' => $budget - $actual,
                'used_percent' => $budget > 0 ? round($actual / $budget * 100) : null,
                'unusual' => $actual > ($plan['rows'][$id]['hi'] ?? INF),
                'overruns' => $this->overruns($id, $month),
            ];
        }
        usort($rows, fn($a, $b) => $a['gap'] <=> $b['gap']);

        $totalActual = array_sum(array_column($rows, 'actual'));
        $installments = $this->installments[$month->format('Y-m')] ?? 0.0;
        [$income, $incomeSource] = $this->income($month);
        $limit = round($income * self::CAP, 2);
        $counted = $this->countedInstallments($installments, $income);
        $excess = $totalActual + $counted - $limit;

        return [
            'month' => $month->format('Y-m'),
            'label' => $month->format('F Y'),
            'budget_source' => $source,
            'income' => $income,
            'income_source' => $incomeSource,
            'limit' => $limit,
            'installments' => $installments,
            'installments_counted' => $counted,
            'total_budget' => array_sum(array_column($rows, 'budget')),
            'total_actual' => $totalActual,
            'actual_percent_of_income' => $income > 0 ? round(($totalActual + $counted) / $income * 100, 1) : null,
            'over_limit' => max(0, $excess),
            'is_current' => $month->isSameMonth(now()),
            'rows' => $rows,
            'cuts' => $this->cuts($rows, $excess),
            'accuracy' => $this->accuracy($month),
        ];
    }

    /** Of the 6 months before $month, how many beat the model's forecast by more than 10%. */
    private function overruns(int $cat, Carbon $month): int
    {
        $m = $this->categoryModel($cat, $month);
        $n = 0;
        foreach ($m['errors'] as $i => $err) {
            $forecast = $m['actuals'][$i] - $err;
            if ($forecast > 0 && $err > 0.1 * $forecast) {
                $n++;
            }
        }

        return $n;
    }

    /** Where to cut to get back under 70%: overspent lines first, WANTS before NEEDS. */
    private function cuts(array $rows, float $excess): array
    {
        if ($excess <= 0) {
            return [];
        }
        $priority = ['WANTS' => 0, 'OTHER' => 1, 'NEEDS' => 2, 'SAVINGS' => 3];
        usort($rows, fn($a, $b) => [$a['gap'] < 0 ? 0 : 1, $priority[$a['rule']], $a['gap']]
            <=> [$b['gap'] < 0 ? 0 : 1, $priority[$b['rule']], $b['gap']]);

        $cuts = [];
        foreach ($rows as $r) {
            if ($excess <= 0 || $r['rule'] === 'SAVINGS' || $r['actual'] <= 0) {
                continue;
            }
            // Overspent: cut back to budget; otherwise trim up to the rule's max share.
            $can = $r['gap'] < 0 ? -$r['gap'] : $r['actual'] * (self::CUT_ORDER[$r['rule']] ?? 0);
            $amount = round(min($excess, $can));
            if ($amount > 0) {
                $cuts[] = ['name' => $r['name'], 'rule' => $r['rule'], 'amount' => $amount];
                $excess -= $amount;
            }
        }

        return $cuts;
    }

    /** Back-test of total spend over the 6 months before $month: mean absolute % error. */
    public function accuracy(Carbon $month): ?float
    {
        $m = $this->model('spend', $this->spend, $month);
        $pct = [];
        foreach ($m['errors'] as $i => $err) {
            if ($m['actuals'][$i] > 0) {
                $pct[] = abs($err) / $m['actuals'][$i];
            }
        }

        return $pct ? round(array_sum($pct) / count($pct) * 100, 1) : null;
    }

    /**
     * Analytics for the month ahead: total-spend forecast with interval, chance of
     * breaking the 70% rule, robust trend, savings rate, cash runway and how the
     * hand-cash installments/investments compare with the allocation targets.
     */
    public function analytics(Carbon $month): array
    {
        $month = $month->copy()->startOfMonth();
        $share = config('finance.income_allocation');
        $plan = $this->predict($month);
        $total = $this->model('spend', $this->spend, $month);
        $spendHistory = $this->series($this->spend, $month);
        $salaryHistory = $this->series($this->salary, $month);

        // P(spend > cap): normal approximation, sigma from the back-test errors.
        $errors = $total['errors'];
        $sigma = count($errors) > 1
            ? sqrt(array_sum(array_map(fn($e) => $e ** 2, $errors)) / (count($errors) - 1))
            : 0;
        $risk = $sigma > 0 ? 1 - TimeSeries::normalCdf(($plan['spend_cap'] - $total['forecast']) / $sigma) : null;

        $rates = [];
        foreach (array_slice(array_keys($salaryHistory), -6) as $i) {
            if ($salaryHistory[$i] > 0) {
                $rates[] = ($salaryHistory[$i] - $spendHistory[$i]) / $salaryHistory[$i];
            }
        }

        $liquid = array_sum(array_intersect_key($this->balances, array_flip(self::LIQUID_ACCOUNTS)));
        $longTerm = array_sum(array_intersect_key($this->balances, array_flip(self::INSTALLMENT_ACCOUNTS)));
        $needs = array_sum(array_map(fn($r) => $r['rule'] === 'NEEDS' ? $r['forecast'] : 0, $plan['rows']));
        $recentInvest = array_slice($this->series($this->invested, $month), -6);

        return [
            'month' => $month->format('Y-m'),
            'label' => $month->format('F Y'),
            'income' => $plan['income'],
            'income_source' => $plan['income_source'],
            'spend_forecast' => round($total['forecast']),
            'spend_lo' => round($total['lo']),
            'spend_hi' => round($total['hi']),
            'spend_method' => $total['method'],
            'weights' => array_map(fn($w) => round($w * 100), $total['weights']),
            'accuracy' => $this->accuracy($month),
            'spend_cap' => $plan['spend_cap'],
            'overspend_risk' => $risk === null ? null : round($risk * 100),
            'trend_per_month' => round(TimeSeries::theilSen(array_slice($spendHistory, -12))),
            'savings_rate' => $rates ? round(TimeSeries::median($rates) * 100, 1) : null,
            'liquid' => round($liquid),
            'long_term' => round($longTerm),
            'invested_balance' => round($this->balances['INVESTMENT'] ?? 0),
            'runway_months' => $total['forecast'] > 0 ? round($liquid / ($total['forecast'] + $plan['installments']), 1) : null,
            'gap' => $plan['gap'],
            'spend_bias' => round($total['bias']),
            'emergency_target' => round(6 * $needs),
            'installments' => $plan['installments'],
            'installments_target' => round($plan['income'] * $share['installments']),
            'savings_target' => round($plan['income'] * $share['savings']),
            'invest_monthly' => round(TimeSeries::median($recentInvest)),
            'invest_target' => round($plan['income'] * $share['invest']),
        ];
    }
}
