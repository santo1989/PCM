<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ExpenseCalculation;
use App\Models\HandCash;
use App\Models\ProjectedExpense;
use App\Services\AI\MLPipeline;
use Illuminate\Http\Request;
use App\Http\Requests\HandCashRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/** Reports, the interactive dashboard and their Excel exports (moved out of HandCashController). */
class ReportController extends Controller
{
    /** Calendar year the salary-investment percentage ramp (30% + 10%/yr, capped 80%) starts counting from. */
    private const INVESTMENT_START_YEAR = 2022;

    public function Yearly_report(Request $request)
    {
        // The month-by-month breakdown, chart, and cards all cover the exact
        // Start/End Date range (which may span multiple calendar years) —
        // one row per calendar month touched by the range, clipped to the
        // range at the first/last month. Bounded by the earliest transaction
        // on record through today.
        $minDataDate = ExpenseCalculation::min('date');
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $rangeStart = Carbon::parse($startDate)->startOfDay();
        $rangeEnd = Carbon::parse($endDate)->endOfDay();
        if ($rangeStart->gt($rangeEnd)) {
            [$rangeStart, $rangeEnd] = [$rangeEnd->copy()->startOfDay(), $rangeStart->copy()->endOfDay()];
        }

        $monthlyData = $this->buildYearlyMonthlyData($rangeStart, $rangeEnd);

        // ---- Detailed analysis ----
        $monthsCollection = collect($monthlyData);
        $totalIncome = $monthsCollection->sum('income');
        $totalExpense = $monthsCollection->sum('expense');

        $monthsWithIncome = $monthsCollection->filter(fn($d) => $d['income'] > 0);
        $monthsWithExpense = $monthsCollection->filter(fn($d) => $d['expense'] > 0);

        // Compared against the immediately preceding period of equal length,
        // since an arbitrary range has no single "previous calendar year".
        $rangeDays = $rangeStart->diffInDays($rangeEnd) + 1;
        $prevRangeEnd = $rangeStart->copy()->subDay()->endOfDay();
        $prevRangeStart = $prevRangeEnd->copy()->subDays($rangeDays - 1)->startOfDay();

        $prevPeriodIncome = (float) ExpenseCalculation::where('types', 'INCOME')
            ->whereBetween('date', [$prevRangeStart->toDateString(), $prevRangeEnd->toDateString()])
            ->sum('amount');
        $prevPeriodExpense = (float) ExpenseCalculation::where('types', 'EXPENSE')
            ->whereBetween('date', [$prevRangeStart->toDateString(), $prevRangeEnd->toDateString()])
            ->sum('amount');

        $analysis = [
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'netTotal' => $totalIncome - $totalExpense,
            'avgSavingsRate' => $totalIncome > 0 ? (($totalIncome - $totalExpense) / $totalIncome) * 100 : 0,
            'bestIncomeMonth' => $monthsWithIncome->sortByDesc('income')->keys()->first(),
            'worstIncomeMonth' => $monthsWithIncome->sortBy('income')->keys()->first(),
            'highestExpenseMonth' => $monthsWithExpense->sortByDesc('expense')->keys()->first(),
            'lowestExpenseMonth' => $monthsWithExpense->sortBy('expense')->keys()->first(),
            'prevRangeStart' => $prevRangeStart->toDateString(),
            'prevRangeEnd' => $prevRangeEnd->toDateString(),
            'prevPeriodIncome' => $prevPeriodIncome,
            'prevPeriodExpense' => $prevPeriodExpense,
            'incomeYoyChange' => $prevPeriodIncome > 0 ? (($totalIncome - $prevPeriodIncome) / $prevPeriodIncome) * 100 : null,
            'expenseYoyChange' => $prevPeriodExpense > 0 ? (($totalExpense - $prevPeriodExpense) / $prevPeriodExpense) * 100 : null,
        ];

        $aiInsights = MLPipeline::run($rangeStart, $rangeEnd);

        return view('backend.reports.yearly_report', compact('monthlyData', 'analysis', 'aiInsights', 'startDate', 'endDate', 'minDataDate'));
    }



    /**
     * One entry per calendar month name (January..December) touched by the
     * range, summed across every year in the range. Each month also keeps a
     * per-year split (clipped to the range) with category-wise totals.
     * Shared by the Yearly report page and its Excel export.
     */
    private function buildYearlyMonthlyData(Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $rangeStartDate = $rangeStart->toDateString();
        $rangeEndDate = $rangeEnd->toDateString();
        $categoryNames = Category::pluck('name', 'id');

        $typeRows = ExpenseCalculation::whereIn('types', ['INCOME', 'EXPENSE'])
            ->whereBetween('date', [$rangeStartDate, $rangeEndDate])
            ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'), 'types', 'category_id')
            ->select(
                DB::raw('YEAR(date) as y'),
                DB::raw('MONTH(date) as m'),
                'types',
                'category_id',
                DB::raw('SUM(amount) as total')
            )
            ->get();

        $ruleRows = ExpenseCalculation::whereIn('rules', ['NEEDS', 'WANTS', 'SAVINGS'])
            ->whereBetween('date', [$rangeStartDate, $rangeEndDate])
            ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'), 'rules')
            ->select(
                DB::raw('YEAR(date) as y'),
                DB::raw('MONTH(date) as m'),
                'rules',
                DB::raw('SUM(amount) as total')
            )
            ->get();

        $emptyBucket = fn() => [
            'income' => 0.0,
            'expense' => 0.0,
            'thisMonthneeds' => 0.0,
            'thisMonthwants' => 0.0,
            'thisMonthsavings' => 0.0,
            'incomeByCategory' => [],
            'expenseByCategory' => [],
        ];

        $monthlyData = [];

        $cursor = $rangeStart->copy()->startOfMonth();
        $lastMonth = $rangeEnd->copy()->startOfMonth();

        while ($cursor->lte($lastMonth)) {
            $m = (int) $cursor->month;
            $y = (int) $cursor->year;
            $monthStart = $cursor->copy()->startOfMonth()->max($rangeStart);
            $monthEnd = $cursor->copy()->endOfMonth()->min($rangeEnd);

            if (!isset($monthlyData[$m])) {
                $monthlyData[$m] = ['month' => $m, 'label' => $cursor->format('F'), 'years' => []] + $emptyBucket();
            }
            $monthlyData[$m]['years'][$y] = [
                'year' => $y,
                'rangeStart' => $monthStart->toDateString(),
                'rangeEnd' => $monthEnd->toDateString(),
            ] + $emptyBucket();

            $cursor->addMonth();
        }
        ksort($monthlyData);

        foreach ($typeRows as $row) {
            $m = (int) $row->m;
            $y = (int) $row->y;
            if (!isset($monthlyData[$m]['years'][$y])) {
                continue;
            }
            $amount = (float) $row->total;
            $field = $row->types === 'INCOME' ? 'income' : 'expense';
            $catField = $field . 'ByCategory';
            $catName = $categoryNames[$row->category_id] ?? 'Uncategorized';

            $monthlyData[$m][$field] += $amount;
            $monthlyData[$m][$catField][$catName] = ($monthlyData[$m][$catField][$catName] ?? 0) + $amount;
            $monthlyData[$m]['years'][$y][$field] += $amount;
            $monthlyData[$m]['years'][$y][$catField][$catName] = ($monthlyData[$m]['years'][$y][$catField][$catName] ?? 0) + $amount;
        }

        foreach ($ruleRows as $row) {
            $m = (int) $row->m;
            $y = (int) $row->y;
            if (!isset($monthlyData[$m]['years'][$y])) {
                continue;
            }
            $field = 'thisMonth' . strtolower($row->rules);
            $monthlyData[$m][$field] += (float) $row->total;
            $monthlyData[$m]['years'][$y][$field] += (float) $row->total;
        }

        foreach ($monthlyData as $m => &$data) {
            arsort($data['incomeByCategory']);
            arsort($data['expenseByCategory']);
            foreach ($data['years'] as &$yearData) {
                arsort($yearData['incomeByCategory']);
                arsort($yearData['expenseByCategory']);
            }
            unset($yearData);

            $data['needs'] = $data['income'] * 0.5;
            $data['wants'] = $data['income'] * 0.3;
            $data['savings'] = $data['income'] * 0.2;
            $data['net'] = $data['income'] - $data['expense'];
        }
        unset($data);

        return $monthlyData;
    }



    public function Monthly_report()
    {
        // Handle date range inputs
        if (request('start_date') && request('end_date')) {
            $startDate = request('start_date');
            $endDate = request('end_date');
        } else {
            $startDate = now()->startOfMonth()->format('Y-m-d');
            $endDate = now()->endOfMonth()->format('Y-m-d');
        }

        // Bounds for the date inputs: earliest transaction on record through today.
        $minDataDate = ExpenseCalculation::min('date');

        $currentMonth = Carbon::parse($startDate)->format('m');
        $currentYear = Carbon::parse($startDate)->format('Y');

        // Income for the month (individual transactions sorted by amount descending)
        $thisMonthIncome = ExpenseCalculation::with('category')
            ->where('types', 'INCOME')
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('amount', 'desc')
            ->get();

        // Income grouped by category (sorted by total amount descending)
        $MonthlyIncomeCategorieswise = ExpenseCalculation::with('category')
            ->where('types', 'INCOME')
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('category_id')
            ->select('category_id', DB::raw('SUM(amount) as totalIncome'))
            ->orderBy('totalIncome', 'desc')
            ->get();

        // Expenses grouped by category (sorted by total amount descending)
        $thisMonthExpense = ExpenseCalculation::with('category')
            ->where('types', 'EXPENSE')
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('category_id')
            ->select('category_id', DB::raw('SUM(amount) as totalExpense'))
            ->orderBy('totalExpense', 'desc')
            ->get();

        // Needs/Wants/Savings totals
        $thisMonthneeds = ExpenseCalculation::where('rules', 'NEEDS')
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        $thisMonthwants = ExpenseCalculation::where('rules', 'WANTS')
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        $thisMonthsavings = ExpenseCalculation::where('rules', 'SAVINGS')
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        // Genuine calendar-year totals for the year the selected period falls in (year-to-date context)
        $thisYearIncome = ExpenseCalculation::with('category')
            ->where('types', 'INCOME')
            ->whereYear('date', $currentYear)
            ->orderBy('amount', 'desc')
            ->get();

        // Calendar-year income grouped by category (sorted by total amount descending)
        $thisYearIncomecategory = ExpenseCalculation::with('category')
            ->where('types', 'INCOME')
            ->whereYear('date', $currentYear)
            ->groupBy('category_id')
            ->select('category_id', DB::raw('SUM(amount) as totalIncomeYear'))
            ->orderBy('totalIncomeYear', 'desc')
            ->get();

        // Calendar-year expenses grouped by category (sorted by total amount descending)
        $thisYearExpense = ExpenseCalculation::with('category')
            ->where('types', 'EXPENSE')
            ->whereYear('date', $currentYear)
            ->groupBy('category_id')
            ->select('category_id', DB::raw('SUM(amount) as totalExpenseYear'))
            ->orderBy('totalExpenseYear', 'desc')
            ->get();

        // ---- Detailed analysis: compare against the immediately preceding period of equal length ----
        $periodDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        $prevStart = Carbon::parse($startDate)->subDays($periodDays)->format('Y-m-d');
        $prevEnd = Carbon::parse($startDate)->subDay()->format('Y-m-d');

        $prevIncome = (float) ExpenseCalculation::where('types', 'INCOME')->whereBetween('date', [$prevStart, $prevEnd])->sum('amount');
        $prevExpense = (float) ExpenseCalculation::where('types', 'EXPENSE')->whereBetween('date', [$prevStart, $prevEnd])->sum('amount');

        $periodIncome = (float) $thisMonthIncome->sum('amount');
        $periodExpense = (float) $thisMonthExpense->sum('totalExpense');

        // Day-of-week spending pattern (MySQL DAYOFWEEK: 1=Sunday..7=Saturday)
        $dayOfWeekRaw = ExpenseCalculation::where('types', 'EXPENSE')
            ->whereBetween('date', [$startDate, $endDate])
            ->select(DB::raw('DAYOFWEEK(date) as dow'), DB::raw('SUM(amount) as total'))
            ->groupBy(DB::raw('DAYOFWEEK(date)'))
            ->pluck('total', 'dow');
        $dayOfWeekLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $dayOfWeekData = [];
        foreach (range(1, 7) as $d) {
            $dayOfWeekData[] = (float) ($dayOfWeekRaw[$d] ?? 0);
        }

        // Same calendar period, one year earlier
        $lastYearStart = Carbon::parse($startDate)->subYear()->format('Y-m-d');
        $lastYearEnd = Carbon::parse($endDate)->subYear()->format('Y-m-d');
        $lastYearPeriodIncome = (float) ExpenseCalculation::where('types', 'INCOME')->whereBetween('date', [$lastYearStart, $lastYearEnd])->sum('amount');
        $lastYearPeriodExpense = (float) ExpenseCalculation::where('types', 'EXPENSE')->whereBetween('date', [$lastYearStart, $lastYearEnd])->sum('amount');

        // Top saving category: biggest expense DECREASE vs. the immediately preceding period, per category
        $prevExpenseByCategory = ExpenseCalculation::where('types', 'EXPENSE')
            ->whereBetween('date', [$prevStart, $prevEnd])
            ->groupBy('category_id')
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->pluck('total', 'category_id');

        $topSavingCategory = null;
        $topSavingAmount = 0.0;
        foreach ($thisMonthExpense as $row) {
            $prevAmount = (float) ($prevExpenseByCategory[$row->category_id] ?? 0);
            $decrease = $prevAmount - (float) $row->totalExpense;
            if ($decrease > $topSavingAmount) {
                $topSavingAmount = $decrease;
                $topSavingCategory = optional($row->category)->name;
            }
        }

        $analysis = [
            'periodDays' => $periodDays,
            'prevStart' => $prevStart,
            'prevEnd' => $prevEnd,
            'prevIncome' => $prevIncome,
            'prevExpense' => $prevExpense,
            'incomeChange' => $prevIncome > 0 ? (($periodIncome - $prevIncome) / $prevIncome) * 100 : null,
            'expenseChange' => $prevExpense > 0 ? (($periodExpense - $prevExpense) / $prevExpense) * 100 : null,
            'savingsRate' => $periodIncome > 0 ? (($periodIncome - $periodExpense) / $periodIncome) * 100 : 0,
            'topExpenseCategory' => $thisMonthExpense->first(),
            'topIncomeCategory' => $MonthlyIncomeCategorieswise->first(),
            'dayOfWeekLabels' => $dayOfWeekLabels,
            'dayOfWeekData' => $dayOfWeekData,
            'lastYearStart' => $lastYearStart,
            'lastYearEnd' => $lastYearEnd,
            'lastYearPeriodIncome' => $lastYearPeriodIncome,
            'lastYearPeriodExpense' => $lastYearPeriodExpense,
            'topSavingCategory' => $topSavingCategory,
            'topSavingAmount' => $topSavingAmount,
        ];

        $aiInsights = MLPipeline::run(Carbon::parse($startDate), Carbon::parse($endDate));

        return view('backend.reports.monthly_report', compact(
            'thisMonthIncome',
            'thisMonthExpense',
            'thisMonthneeds',
            'thisMonthwants',
            'thisMonthsavings',
            'thisYearIncome',
            'thisYearExpense',
            'startDate',
            'endDate',
            'currentMonth',
            'currentYear',
            'thisYearIncomecategory',
            'MonthlyIncomeCategorieswise',
            'analysis',
            'aiInsights',
            'minDataDate'
        ));
    }



    public function power_bi_report()
    {
        return view('backend.reports.power_bi_report');
    }


    /**
     * Salary split per the 70/20/10 rule (config finance.income_allocation), target vs.
     * actual for each month in the range plus a total row.
     * Actuals: needs/wants/installments = EXPENSE entries by rule NEEDS/WANTS/SAVINGS;
     * invest = net deposits into the INVESTMENT (stocks) account;
     * savings = salary - expenses - invest (what stayed in Bank/DPS/FD).
     */
    private function incomeAllocation(string $startDate, string $endDate): array
    {
        $share = config('finance.income_allocation');
        $ym = DB::raw("DATE_FORMAT(date, '%Y-%m')");

        $salary = ExpenseCalculation::where('types', 'INCOME')->where('category_id', 1)
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy($ym)->selectRaw("DATE_FORMAT(date, '%Y-%m') as ym, SUM(amount) as total")
            ->pluck('total', 'ym');
        $expense = ExpenseCalculation::where('types', 'EXPENSE')
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy($ym, 'rules')->selectRaw("DATE_FORMAT(date, '%Y-%m') as ym, rules, SUM(amount) as total")
            ->get()->groupBy('ym');
        $invest = HandCash::where('rules', 'INVESTMENT')
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy($ym)
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as ym, SUM(CASE WHEN types = 'SAVE' THEN amount ELSE -amount END) as total")
            ->pluck('total', 'ym');

        $rows = [];
        $cursor = Carbon::parse($startDate)->startOfMonth();
        while ($cursor->lte(Carbon::parse($endDate))) {
            $key = $cursor->format('Y-m');
            $byRule = collect($expense[$key] ?? [])->pluck('total', 'rules');
            $income = (float) ($salary[$key] ?? 0);
            $actual = [
                'needs' => (float) ($byRule['NEEDS'] ?? 0),
                'wants' => (float) ($byRule['WANTS'] ?? 0),
                'installments' => (float) ($byRule['SAVINGS'] ?? 0),
                'invest' => (float) ($invest[$key] ?? 0),
            ];
            $totalExpense = (float) collect($expense[$key] ?? [])->sum('total');
            $actual['savings'] = $income - $totalExpense - $actual['invest'];
            $actual['expense'] = $totalExpense;

            $rows[$key] = [
                'label' => $cursor->format('M Y'),
                'salary' => $income,
                'target' => array_map(fn($p) => $income * $p, $share) + ['expense' => $income * ($share['needs'] + $share['wants'] + $share['installments'])],
                'actual' => $actual,
            ];
            $cursor->addMonth();
        }

        $sum = fn(string $part, string $k) => array_sum(array_map(fn($r) => $r[$part][$k], $rows));
        $keys = ['needs', 'wants', 'installments', 'expense', 'savings', 'invest'];
        $total = [
            'label' => 'Total',
            'salary' => array_sum(array_column($rows, 'salary')),
            'target' => array_combine($keys, array_map(fn($k) => $sum('target', $k), $keys)),
            'actual' => array_combine($keys, array_map(fn($k) => $sum('actual', $k), $keys)),
        ];

        return ['share' => $share, 'months' => $rows, 'total' => $total];
    }

    public function Monthly_invest()
    {
        // Date range handling
        // Get min and max dates from the database
        $minDate = ExpenseCalculation::min('date');
        $maxDate = ExpenseCalculation::max('date');

        // Handle start and end dates with proper parsing
        $startDate = request('start_date') ?? ($minDate ? Carbon::parse($minDate)->format('Y-m-d') : now()->format('Y-m-d'));
        $endDate = request('end_date') ?? ($maxDate ? Carbon::parse($maxDate)->format('Y-m-d') : now()->format('Y-m-d'));


        // Get income data with investment calculations
        // $incomes = ExpenseCalculation::with('category')
        //     ->where('types', 'income')
        $incomes = ExpenseCalculation::with('category')
            ->where('category_id', 1)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('amount', 'desc')
            ->get()
            ->map(function ($item) {
                $amount = (float) $item->amount;
                $share = config('finance.income_allocation');

                // 70/20/10 rule (expense 70% split 50/30/20), see config/finance.php.
                return [
                    'date' => $item->date,
                    'amount' => $amount,
                    'investment' => $amount * $share['invest'],
                    'needs' => $amount * $share['needs'],
                    'wants' => $amount * $share['wants'],
                    'future' => $amount * $share['installments'],
                    'savings' => $amount * $share['savings'],
                ];
            });

        // Expense calculations
        $expenses = ExpenseCalculation::with('category')
            ->where('types', 'EXPENSE')
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy('rules');

        // Convert amounts to float for consistent arithmetic in the view
        $expenses = $expenses->map(function ($group) {
            return $group->map(function ($item) {
                $item->amount = (float) $item->amount;
                return $item;
            });
        });

        $totalExpenses = $expenses->flatten()->sum('amount');

        $investmentGrowth = $this->calculateInvestmentGrowth();
        $allocation = $this->incomeAllocation($startDate, $endDate);

        // ---- Detailed analysis ----
        $totalIncome = (float) $incomes->sum('amount');
        $totalInvestment = (float) $incomes->sum('investment');
        $currentInvestmentPercent = config('finance.income_allocation.invest') * 100;

        // Which expense "rule" (Needs/Wants/Savings) dominates spending in the period
        $expenseRuleTotals = $expenses->map(fn($group) => $group->sum('amount'))->sortDesc();

        // Asset allocation: current net balance per investment-like HandCash rule
        // (excludes plain cash/checking/mobile-wallet/loan/credit-card rules).
        $investmentLikeRules = ['DPS', 'ISLAMIC_DPS', 'FD', 'INVESTMENT'];
        $assetAllocation = [];
        foreach ($investmentLikeRules as $rule) {
            $save = (float) HandCash::where('rules', $rule)->where('types', 'SAVE')->sum('amount');
            $withdraw = (float) HandCash::where('rules', $rule)->where('types', 'WIDROWS')->sum('amount');
            $balance = $save - $withdraw;
            if ($balance > 0) {
                $assetAllocation[$rule] = $balance;
            }
        }
        $currentInvestedBalance = array_sum($assetAllocation);

        // Compound growth projection: this app has no market-price data, so the
        // return rate below is a clearly-labeled ASSUMPTION (a common long-run
        // equity-market average), not derived from real performance.
        $assumedAnnualReturn = 0.07;
        $monthlyRate = $assumedAnnualReturn / 12;
        $periodMonths = max(Carbon::parse($startDate)->diffInMonths(Carbon::parse($endDate)), 1);
        $avgMonthlyInvestment = $totalInvestment / $periodMonths;

        $compoundProjection = [];
        foreach ([5, 10, 20] as $yearsOut) {
            $n = $yearsOut * 12;
            $futureContributions = $avgMonthlyInvestment > 0
                ? $avgMonthlyInvestment * ((pow(1 + $monthlyRate, $n) - 1) / $monthlyRate)
                : 0;
            $futureExistingBalance = $currentInvestedBalance * pow(1 + $monthlyRate, $n);
            $compoundProjection[$yearsOut] = $futureContributions + $futureExistingBalance;
        }

        // 4% rule: a portfolio of ~25x annual expenses is considered sustainable
        // indefinitely at a 4% annual withdrawal rate.
        $avgMonthlyExpense = $totalExpenses / $periodMonths;
        $fourPercentTarget = $avgMonthlyExpense * 12 * 25;

        $yearsToFourPercentTarget = null;
        if ($fourPercentTarget > 0 && ($avgMonthlyInvestment > 0 || $currentInvestedBalance > 0)) {
            $balance = $currentInvestedBalance;
            $months = 0;
            $maxMonths = 100 * 12;
            while ($balance < $fourPercentTarget && $months < $maxMonths) {
                $balance = $balance * (1 + $monthlyRate) + $avgMonthlyInvestment;
                $months++;
            }
            $yearsToFourPercentTarget = $months < $maxMonths ? round($months / 12, 1) : null;
        }

        $analysis = [
            'totalIncome' => $totalIncome,
            'totalExpenses' => $totalExpenses,
            'netPosition' => $totalIncome - $totalExpenses,
            'totalInvestment' => $totalInvestment,
            'investmentRatePercent' => $currentInvestmentPercent,
            'actualSavingsRatePercent' => $totalIncome > 0 ? (($totalIncome - $totalExpenses) / $totalIncome) * 100 : 0,
            'topExpenseRule' => $expenseRuleTotals->keys()->first(),
            'topExpenseRuleAmount' => $expenseRuleTotals->first(),
            'yearsInvesting' => max(now()->year - self::INVESTMENT_START_YEAR, 0),
            'assetAllocation' => $assetAllocation,
            'currentInvestedBalance' => $currentInvestedBalance,
            'assumedAnnualReturn' => $assumedAnnualReturn,
            'compoundProjection' => $compoundProjection,
            'fourPercentTarget' => $fourPercentTarget,
            'yearsToFourPercentTarget' => $yearsToFourPercentTarget,
        ];

        $aiInsights = MLPipeline::run(Carbon::parse($startDate), Carbon::parse($endDate));

        return view(
            'backend.reports.Monthly_invest',
            compact('incomes', 'expenses', 'startDate', 'endDate', 'investmentGrowth', 'totalExpenses', 'analysis', 'aiInsights', 'minDate', 'allocation')
        );
    }

    private function calculateInvestmentGrowth()
    {
        // Yearly investment growth from the employment start year through the current year,
        // using the same 30% base + 10%/yr ramp (capped 80%) as the per-transaction calculation above.
        $startYear = self::INVESTMENT_START_YEAR;
        $currentYear = now()->year;

        $growthData = [];

        for ($year = $startYear; $year <= $currentYear; $year++) {
            $yearlyIncome = (float) ExpenseCalculation::whereYear('date', $year)
                ->where('types', 'INCOME')->where('category_id', 1)
                ->sum('amount');

            $yearsActive = $year - $startYear;
            $investmentPercent = min(0.3 * pow(1.1, $yearsActive), 0.8);

            $growthData[] = [
                'year' => $year,
                'amount' => $yearlyIncome * $investmentPercent,
            ];
        }

        return [
            'years' => array_column($growthData, 'year'),
            'amounts' => array_map('floatval', array_column($growthData, 'amount')),
        ];
    }
    // public function Budge_Projection()
    // {
    //     // Retrieve all categories, excluding specified ones
    //     $categories = Category::all()->except([1, 19, 20, 23]);

    //     // Calculate this year's average expenses per category (excluding current month)
    //     $allYearExpenses = ExpenseCalculation::where('types', 'expense')
    //         // ->whereYear('date', Carbon::now()->year)
    //         // ->whereMonth('date', '!=', Carbon::now()->month)
    //         ->groupBy('category_id')
    //         ->select('category_id', DB::raw('sum(amount) as totalExpense'), DB::raw('count(distinct MONTH(date)) as totalMonths'))
    //         ->get();

    //     $thisYearExpense = [];
    //     foreach ($allYearExpenses as $expense) {
    //         $averageExpense = $expense->totalExpense / $expense->totalMonths;
    //         $thisYearExpense[] = [
    //             'category_id' => $expense->category_id,
    //             'averageExpense' => ceil($averageExpense),
    //         ];
    //     }

    //     // Get last month's expenses per category
    //     $lastMonth = date('m') == '01' ? '12' : str_pad(date('m') - 1, 2, '0', STR_PAD_LEFT);
    //     $lastYear = date('m') == '01' ? date('Y') - 1 : date('Y');

    //     $lastMonthExpense = ExpenseCalculation::whereYear('date', $lastYear)
    //         ->whereMonth('date', $lastMonth)
    //         ->where('types', 'EXPENSE')
    //         ->groupBy('category_id')
    //         ->select('category_id', DB::raw('SUM(amount) as totalExpense'))
    //         ->get()
    //         ->map(function ($expense) {
    //             $expense->totalExpense = ceil($expense->totalExpense);
    //             return $expense;
    //         });

    //     $totallastMonthExpense = $lastMonthExpense->sum('totalExpense');

    //     // Get total income for the current month
    //     $totalMonthlyIncome = ExpenseCalculation::where('category_id', 1)
    //         ->whereYear('date', now()->year)
    //         ->whereMonth('date', now()->month)
    //         ->sum('amount');

    //     // Calculate monthly actual limit according to finance rules:
    //     // MonthlyactualLimitExpense = (monthly salary - DPS - Islamic_DPS) * 70%
    //     // Use DPS and Islamic_DPS contributions for the current month (types = 'Save')
    //     $dpsThisMonth = HandCash::where('rules', 'DPS')
    //         ->where('types', 'SAVE')
    //         ->whereYear('date', now()->year)
    //         ->whereMonth('date', now()->month)
    //         ->sum('amount');

    //     $islamicDpsThisMonth = HandCash::where('rules', 'ISLAMIC_DPS')
    //         ->where('types', 'SAVE')
    //         ->whereYear('date', now()->year)
    //         ->whereMonth('date', now()->month)
    //         ->sum('amount');

    //     $MonthlyactualLimitExpense = max(0, ($totalMonthlyIncome - $dpsThisMonth - $islamicDpsThisMonth) * 0.7);

    //     // This is a new part of the code to get the projected expenses for this month
    //     $thisMonthProjectedExpenses = ProjectedExpense::whereYear('date', now()->year)
    //         ->whereMonth('date', now()->month)
    //         ->get()
    //         ->keyBy('category_id');

    //     return view('backend.reports.projection_report', compact(
    //         'categories',
    //         'thisYearExpense',
    //         'lastMonthExpense',
    //         'totallastMonthExpense',
    //         'MonthlyactualLimitExpense',
    //         'totalMonthlyIncome',
    //         'thisMonthProjectedExpenses'
    //     ));
    // }

    /**
     * Interactive dashboard view
     */

    public function Budge_Projection()
    {
        // Retrieve only expense categories (income categories don't get a spending projection)
        $categories = Category::where('types', 'EXPENSE')->get();

        // Optional date range: narrows the "Avg Expense" column and the AI Insights panel
        // below. Everything else on this page (this month's income, last month's expense,
        // next month's projection) stays tied to the current/adjacent calendar month by
        // design — a budget projection tool, not a free-range report. Left blank by default
        // (rather than defaulting to the current month) since "Avg Expense" is more useful
        // as a genuine all-time average than a single month's total.
        $startDate = request('start_date');
        $endDate = request('end_date');
        $hasDateRange = $startDate && $endDate;
        $minDataDate = ExpenseCalculation::min('date');

        // Avg / last-month / saved-budget columns (shared with the Excel export).
        $columns = \App\Services\BudgetPredictor::historyColumns($hasDateRange ? $startDate : null, $hasDateRange ? $endDate : null);

        // ========== NEW: Multi-Year Monthly Averages ==========
        // Get multi-year monthly averages for each category
        $multiYearMonthlyAverages = ExpenseCalculation::where('types', 'EXPENSE')
            ->whereIn('category_id', $categories->pluck('id')->toArray())
            ->groupBy('category_id', DB::raw('MONTH(date)'))
            ->select(
                'category_id',
                DB::raw('MONTH(date) as month'),
                DB::raw('AVG(amount) as avg_amount'),
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('COUNT(DISTINCT YEAR(date)) as years_count'),
                DB::raw('COUNT(*) as transaction_count')
            )
            ->orderBy('category_id')
            ->orderBy('month')
            ->get();

        // Format multi-year monthly averages
        $formattedMultiYearAverages = [];
        foreach ($multiYearMonthlyAverages as $data) {
            $formattedMultiYearAverages[$data->category_id][$data->month] = [
                'month' => $data->month,
                'month_name' => Carbon::create()->month($data->month)->format('F'),
                'avg_amount' => ceil($data->avg_amount),
                'total_amount' => ceil($data->total_amount),
                'years_count' => $data->years_count,
                'transaction_count' => $data->transaction_count
            ];
        }

        // Get overall multi-year monthly averages (across all categories)
        $overallMultiYearMonthlyAverages = ExpenseCalculation::where('types', 'EXPENSE')
            ->whereIn('category_id', $categories->pluck('id')->toArray())
            ->groupBy(DB::raw('MONTH(date)'))
            ->select(
                DB::raw('MONTH(date) as month'),
                DB::raw('AVG(amount) as avg_amount'),
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('COUNT(DISTINCT YEAR(date)) as years_count'),
                DB::raw('COUNT(*) as transaction_count')
            )
            ->orderBy('month')
            ->get();

        $formattedOverallMultiYearAverages = [];
        foreach ($overallMultiYearMonthlyAverages as $data) {
            $formattedOverallMultiYearAverages[$data->month] = [
                'month' => $data->month,
                'month_name' => Carbon::create()->month($data->month)->format('F'),
                'avg_amount' => ceil($data->avg_amount),
                'total_amount' => ceil($data->total_amount),
                'years_count' => $data->years_count,
                'transaction_count' => $data->transaction_count
            ];
        }

        // Get total income for the current month
        $totalMonthlyIncome = ExpenseCalculation::where('category_id', 1)
            ->whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->sum('amount');

        // Calculate monthly actual limit according to finance rules
        $dpsThisMonth = HandCash::where('rules', 'DPS')
            ->where('types', 'SAVE')
            ->whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->sum('amount');

        $islamicDpsThisMonth = HandCash::where('rules', 'ISLAMIC_DPS')
            ->where('types', 'SAVE')
            ->whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->sum('amount');

        $MonthlyactualLimitExpense = max(0, ($totalMonthlyIncome - $dpsThisMonth - $islamicDpsThisMonth) * 0.7);

        $aiRangeStart = $hasDateRange ? Carbon::parse($startDate)->startOfDay() : now()->startOfMonth();
        $aiRangeEnd = $hasDateRange ? Carbon::parse($endDate)->endOfDay() : now()->endOfMonth();
        $aiInsights = MLPipeline::run($aiRangeStart, $aiRangeEnd);
        $nextMonth = now()->addMonthNoOverflow()->startOfMonth();
        $reduction = max(0, min(0.9, (float) request('reduction_percent', 0)));
        $predictor = null;
        $make = function () use (&$predictor) { return $predictor ??= new \App\Services\BudgetPredictor(); };
        $prediction = \App\Services\BudgetPredictor::cached("predict-$reduction", $nextMonth, fn() => $make()->predict($nextMonth, $reduction));
        $budgetAnalytics = \App\Services\BudgetPredictor::cached('analytics', $nextMonth, fn() => $make()->analytics($nextMonth));

        return view('backend.reports.projection_report', compact(
            'prediction',
            'budgetAnalytics',
            'columns',
            'categories',
            'formattedMultiYearAverages',
            'formattedOverallMultiYearAverages',
            'MonthlyactualLimitExpense',
            'totalMonthlyIncome',
            'aiInsights',
            'startDate',
            'endDate',
            'hasDateRange',
            'minDataDate'
        ));
    }
    public function interactiveDashboard()
    {
        return view('backend.reports.interactive_dashboard', [
            'minDataDate' => ExpenseCalculation::min('date'),
            'startDate' => request('start_date', now()->startOfMonth()->toDateString()),
            'endDate' => request('end_date', now()->toDateString()),
            'outlook' => MLPipeline::run(
                Carbon::parse(request('start_date', now()->startOfMonth()->toDateString()))->startOfDay(),
                Carbon::parse(request('end_date', now()->toDateString()))->endOfDay()
            )['outlook'] ?? null,
        ]);
    }

    /**
     * JSON: AI insights (summary, anomalies, forecast, patterns, recommendations)
     * for the selected date range — polled by the dashboard's AI panel.
     */
    public function interactiveDashboardAI(Request $request)
    {
        $start = $request->filled('start_date') ? Carbon::parse($request->get('start_date'))->startOfDay() : now()->startOfMonth();
        $end = $request->filled('end_date') ? Carbon::parse($request->get('end_date'))->endOfDay() : now()->endOfMonth();

        return response()->json(MLPipeline::run($start, $end));
    }

    /**
     * JSON: high-level summary (totals) for the selected date range
     */
    public function interactiveDashboardSummary(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->endOfMonth()->toDateString());
        $cacheKey = "dashboard:summary:{$startDate}:{$endDate}";
        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($startDate, $endDate) {
            $totalIncome = ExpenseCalculation::where('types', 'INCOME')
                ->whereBetween('date', [$startDate, $endDate])
                ->sum('amount');

            $totalExpense = ExpenseCalculation::where('types', 'EXPENSE')
                ->whereBetween('date', [$startDate, $endDate])
                ->sum('amount');

            // Cash balances grouped by rules (hand cash) — all-time, not scoped to the range
            $cashBalances = HandCash::select('rules', DB::raw('SUM(CASE WHEN types = "SAVE" THEN amount ELSE 0 END) - SUM(CASE WHEN types = "WIDROWS" THEN amount ELSE 0 END) as balance'))
                ->groupBy('rules')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->rules => (float) $item->balance];
                });

            return [
                'totalIncome' => (float) $totalIncome,
                'totalExpense' => (float) $totalExpense,
                'cashBalances' => $cashBalances,
                'net' => (float) ($totalIncome - $totalExpense),
            ];
        });

        return response()->json(array_merge(['start_date' => $startDate, 'end_date' => $endDate], $data));
    }

    /**
     * JSON: monthly trend for the last 12 months
     */
    public function interactiveDashboardMonthlyTrend(Request $request)
    {
        $cacheKey = 'dashboard:monthly_trend:last12';
        $payload = Cache::remember($cacheKey, now()->addMinutes(5), function () {
            $end = now();
            $start = now()->subMonths(11);

            $months = [];
            $incomeSeries = [];
            $expenseSeries = [];

            for ($dt = $start->copy(); $dt->lte($end); $dt->addMonth()) {
                $m = $dt->month;
                $y = $dt->year;
                $label = $dt->format('Y-m');
                $months[] = $label;

                $incomeSeries[] = (float) ExpenseCalculation::where('types', 'INCOME')
                    ->whereYear('date', $y)
                    ->whereMonth('date', $m)
                    ->sum('amount');

                $expenseSeries[] = (float) ExpenseCalculation::where('types', 'EXPENSE')
                    ->whereYear('date', $y)
                    ->whereMonth('date', $m)
                    ->sum('amount');
            }

            return ['months' => $months, 'income' => $incomeSeries, 'expense' => $expenseSeries];
        });

        return response()->json($payload);
    }

    /**
     * JSON: category breakdown for the selected date range
     */
    public function interactiveDashboardCategoryBreakdown(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->endOfMonth()->toDateString());
        $cacheKey = "dashboard:category_breakdown:{$startDate}:{$endDate}";
        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($startDate, $endDate) {
            $items = ExpenseCalculation::where('types', 'EXPENSE')
                ->whereBetween('date', [$startDate, $endDate])
                ->groupBy('category_id')
                ->select('category_id', DB::raw('SUM(amount) as total'))
                ->orderBy('total', 'desc')
                ->get();

            // eager load categories in bulk
            $categoryIds = $items->pluck('category_id')->unique()->filter()->values()->all();
            $categories = Category::whereIn('id', $categoryIds)->get()->keyBy('id');

            return $items->map(function ($item) use ($categories) {
                $category = $categories->get($item->category_id);
                return [
                    'category_id' => $item->category_id,
                    'category' => $category ? $category->name : 'Unknown',
                    'total' => (float) $item->total,
                ];
            })->values();
        });

        return response()->json($data);
    }

    /**
     * JSON: savings vs loans (hand cash rules) summary
     */
    public function interactiveDashboardSavingsLoans(Request $request)
    {
        // total savings and total loans across HandCash
        $savings = HandCash::where('types', 'SAVE')->sum('amount');
        $withdrawals = HandCash::where('types', 'WIDROWS')->sum('amount');

        // separate out Loan rules if present
        $loan_in = HandCash::where('rules', 'LOAN')->where('types', 'SAVE')->sum('amount');
        $loan_out = HandCash::where('rules', 'LOAN')->where('types', 'WIDROWS')->sum('amount');

        return response()->json([
            'savings_total' => (float) $savings,
            'withdrawals_total' => (float) $withdrawals,
            'loan_in' => (float) $loan_in,
            'loan_out' => (float) $loan_out,
        ]);
    }

    /**
     * JSON: top expense categories for the selected date range
     */
    public function interactiveDashboardTopCategories(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->endOfMonth()->toDateString());
        $limit = (int) $request->get('limit', 8);

        $items = ExpenseCalculation::where('types', 'EXPENSE')
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('category_id')
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->orderBy('total', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                $c = Category::find($item->category_id);
                return [
                    'category' => $c ? $c->name : 'Unknown',
                    'total' => (float) $item->total,
                ];
            });

        return response()->json($items);
    }

    /**
     * JSON: running balance (hand cash chronologically)
     */
    public function interactiveDashboardRunningBalance(Request $request)
    {
        // fetch last 200 handcash transactions ordered by date
        $rows = HandCash::orderBy('date', 'asc')->orderBy('id', 'asc')->limit(1000)->get(['date', 'name', 'rules', 'types', 'amount']);

        $balance = 0.0;
        $series = [];
        foreach ($rows as $r) {
            $amt = (float) $r->amount;
            if (strtolower($r->types) === 'save') $balance += $amt;
            else $balance -= $amt;
            $series[] = [
                'date' => $r->date,
                'name' => $r->name,
                'rules' => $r->rules,
                'types' => $r->types,
                'amount' => $amt,
                'balance' => round($balance, 2),
            ];
        }

        return response()->json($series);
    }

    /**
     * JSON: recent transactions (expense/income)
     */
    public function interactiveDashboardRecentTransactions(Request $request)
    {
        $limit = (int) $request->get('limit', 20);
        $rows = ExpenseCalculation::with('category')
            ->orderBy('date', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'date' => $r->date,
                    'name' => $r->name,
                    'category' => $r->category->name ?? null,
                    'types' => $r->types,
                    'amount' => (float) $r->amount,
                ];
            });

        return response()->json($rows);
    }

    public function calculateAndSaveBudget()
    {
        // Next month's budget from BudgetPredictor (seasonal run-rate, learned bias,
        // cut to 70% of salary). Values the user edited in the Preview modal win.
        $nextMonth = Carbon::now()->addMonthNoOverflow()->startOfMonth();
        $plan = (new \App\Services\BudgetPredictor())->predict($nextMonth, floatval(request()->get('reduction_percent', 0.10)));

        if ($plan['income'] <= 0) {
            return back()->with('error_message', 'No salary found in the last months. Cannot calculate budget.');
        }

        $posted = collect(request()->input('projectedExpense', []))
            ->map(fn($v) => (float) str_replace(',', '', (string) $v))
            ->filter(fn($v) => $v > 0);
        $amounts = $posted->isNotEmpty() ? $posted->all() : array_column($plan['rows'], 'budget', 'category_id');
        $validIds = Category::where('types', 'EXPENSE')->pluck('id')->map(fn($id) => (int) $id)->all();

        $projectedExpensesData = [];
        foreach ($amounts as $catId => $amount) {
            if (in_array((int) $catId, $validIds, true)) {
                $projectedExpensesData[] = [
                    'category_id' => (int) $catId,
                    'date' => $nextMonth,
                    'amount' => (int) ceil($amount),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Replace (not duplicate) any budget already saved for next month.
        ProjectedExpense::whereYear('date', $nextMonth->year)
            ->whereMonth('date', $nextMonth->month)
            ->delete();

        // Save the new projected budget
        if (!empty($projectedExpensesData)) {
            // Bulk insert skips model events, so stamp the owner here.
            ProjectedExpense::insert(array_map(fn($row) => $row + ['user_id' => auth()->id()], $projectedExpensesData));
            \App\Services\FinancialAnalyticsService::forgetAll();
            \App\Services\FinancialAnalysisService::forgetAll();
        }

        return back()->with('message', 'Dynamic budget for next month has been calculated and saved successfully!');
    }

    /**
     * Excel exports for the report pages.
     */

    public function exportYearlyReport(Request $request)
    {
        $rangeStart = Carbon::parse($request->get('start_date', now()->startOfMonth()->toDateString()))->startOfDay();
        $rangeEnd = Carbon::parse($request->get('end_date', now()->toDateString()))->endOfDay();
        if ($rangeStart->gt($rangeEnd)) {
            [$rangeStart, $rangeEnd] = [$rangeEnd->copy()->startOfDay(), $rangeStart->copy()->endOfDay()];
        }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\YearlyReportExport(
                $this->buildYearlyMonthlyData($rangeStart, $rangeEnd),
                $rangeStart->toDateString(),
                $rangeEnd->toDateString()
            ),
            "yearly-report-{$rangeStart->toDateString()}-to-{$rangeEnd->toDateString()}.xlsx"
        );
    }

    public function exportMonthlyReport(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->endOfMonth()->format('Y-m-d'));

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\MonthlyReportExport($startDate, $endDate),
            "monthly-report-{$startDate}-to-{$endDate}.xlsx"
        );
    }

    public function exportMonthlyInvest(Request $request)
    {
        $minDate = ExpenseCalculation::min('date');
        $maxDate = ExpenseCalculation::max('date');

        $startDate = $request->get('start_date', $minDate ? Carbon::parse($minDate)->format('Y-m-d') : now()->format('Y-m-d'));
        $endDate = $request->get('end_date', $maxDate ? Carbon::parse($maxDate)->format('Y-m-d') : now()->format('Y-m-d'));

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\MonthlyInvestExport($startDate, $endDate),
            "monthly-investment-{$startDate}-to-{$endDate}.xlsx"
        );
    }

    public function exportBudgeProjection(Request $request)
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BudgetProjectionExport($request->get('start_date'), $request->get('end_date')),
            'budget-projection-' . now()->format('Y-m') . '.xlsx'
        );
    }

    public function exportInteractiveDashboard(Request $request)
    {
        $year = (int) $request->get('year', now()->year);
        $month = $request->get('month') ? (int) $request->get('month') : null;

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\InteractiveDashboardExport($year, $month),
            "interactive-dashboard-{$year}" . ($month ? "-{$month}" : '') . '.xlsx'
        );
    }
}
