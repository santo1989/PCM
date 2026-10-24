<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Models\ExpenseCalculation;
use App\Models\HandCash;
use App\Models\Category;
use Illuminate\Support\Facades\DB;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrap();
        // Move heavy DB queries out of Blade by composing data for the dashboard
        View::composer('backend.home', function ($view) {
            // Date range replaces the old Year/Month selectors: the year/month they cover
            // are derived from the End Date, defaulting to the current month. Bounded by
            // the earliest transaction on record through today.
            $minDataDate = ExpenseCalculation::min('date');
            $startDate = request('start_date') ?? now()->startOfMonth()->toDateString();
            $endDate = request('end_date') ?? now()->toDateString();

            $currentYear = (int) \Carbon\Carbon::parse($endDate)->year;
            $currentMonth = (int) \Carbon\Carbon::parse($endDate)->month;

            // Last 12 months summary + per-month category totals for the detail modals,
            // from one grouped query (was 6 queries per month plus 2 per category per month).
            $grouped = ExpenseCalculation::whereYear('date', $currentYear)
                ->groupBy(DB::raw('MONTH(date)'), 'types', 'rules', 'category_id')
                ->select(DB::raw('MONTH(date) as m'), 'types', 'rules', 'category_id', DB::raw('SUM(amount) as total'))
                ->get();

            $monthlyData = [];
            $monthCategoryTotals = [];
            for ($month = 1; $month <= 12; $month++) {
                $rows = $grouped->where('m', $month);
                $salary = (float) $rows->where('types', 'INCOME')->where('category_id', 1)->sum('total');
                $monthlyData[$month] = [
                    'income' => (float) $rows->where('types', 'INCOME')->sum('total'),
                    'needs' => $salary * 0.5,
                    'wants' => $salary * 0.3,
                    'savings' => $salary * 0.2,
                    'expense' => (float) $rows->where('types', 'EXPENSE')->sum('total'),
                    'thisMonthneeds' => (float) $rows->where('rules', 'NEEDS')->sum('total'),
                    'thisMonthwants' => (float) $rows->where('rules', 'WANTS')->sum('total'),
                    'thisMonthsavings' => (float) $rows->where('rules', 'SAVINGS')->sum('total'),
                ];
                foreach (['INCOME', 'EXPENSE'] as $type) {
                    $monthCategoryTotals[$month][$type] = $rows->where('types', $type)
                        ->groupBy('category_id')
                        ->map(fn($g) => (float) $g->sum('total'))
                        ->filter(fn($total) => $total != 0)
                        ->sortDesc()
                        ->all();
                }
            }

            // Exports tab data
            $thisMonthtotalIncome = ExpenseCalculation::where('types', 'INCOME')
                ->whereMonth('date', $currentMonth)
                ->whereYear('date', $currentYear)
                ->sum('amount');
            $thisMonthIncome = ExpenseCalculation::where('types', 'INCOME')
                ->whereMonth('date', $currentMonth)
                ->whereYear('date', $currentYear)
                ->groupBy('category_id')
                ->select('category_id', DB::raw('SUM(amount) as totalIncome'))
                ->orderBy('totalIncome', 'desc')
                ->get();

            $thisMonthExpense = ExpenseCalculation::where('types', 'EXPENSE')
                ->whereMonth('date', $currentMonth)
                ->whereYear('date', $currentYear)
                ->groupBy('category_id')
                ->select('category_id', DB::raw('SUM(amount) as totalExpense'))
                ->orderBy('totalExpense', 'desc')
                ->get();

            $thisMonthneeds = ExpenseCalculation::Where('rules', 'NEEDS')
                ->whereMonth('date', $currentMonth)
                ->whereYear('date', $currentYear)
                ->sum('amount');

            $thisMonthwants = ExpenseCalculation::Where('rules', 'WANTS')
                ->whereMonth('date', $currentMonth)
                ->whereYear('date', $currentYear)
                ->sum('amount');

            $thisMonthsavings = ExpenseCalculation::Where('rules', 'SAVINGS')
                ->whereMonth('date', $currentMonth)
                ->whereYear('date', $currentYear)
                ->sum('amount');

            $thisYeartotalIncome = ExpenseCalculation::where('types', 'INCOME')
                ->whereYear('date', $currentYear)
                ->sum('amount');

            $thisYearIncome = ExpenseCalculation::Where('types', 'INCOME')
                ->whereYear('date', $currentYear)
                ->groupBy('category_id')
                ->select('category_id', DB::raw('SUM(amount) as totalIncomeYear'))
                ->orderBy('totalIncomeYear', 'desc')
                ->get();

            $thisYearExpense = ExpenseCalculation::Where('types', 'EXPENSE')
                ->whereYear('date', $currentYear)
                ->groupBy('category_id')
                ->select('category_id', DB::raw('SUM(amount) as totalExpenseYear'))
                ->orderBy('totalExpenseYear', 'desc')
                ->get();

            // Build a category id->name map for the view to avoid repeated lookups
            $categoryIds = collect([]);
            $categoryIds = $categoryIds->merge(collect($thisMonthExpense)->pluck('category_id'));
            $categoryIds = $categoryIds->merge(collect($thisYearExpense)->pluck('category_id'));
            $categoryIds = $categoryIds->unique()->filter()->values()->all();
            $categoryMap = [];
            if (!empty($categoryIds)) {
                $categoryMap = Category::whereIn('id', $categoryIds)->get()->keyBy('id')->map(function ($c) {
                    return $c->name;
                })->toArray();
            }

            $insights = \App\Services\InsightEngine::dashboardSummary();

            $view->with(compact('monthlyData', 'thisMonthIncome', 'thisMonthExpense', 'thisMonthneeds', 'thisMonthwants', 'thisMonthsavings', 'thisYearIncome', 'thisYearExpense', 'categoryMap', 'monthCategoryTotals', 'currentMonth', 'currentYear', 'thisMonthtotalIncome', 'thisYeartotalIncome', 'insights', 'startDate', 'endDate', 'minDataDate'));
        });
    }
}
