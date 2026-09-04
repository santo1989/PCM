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

            // Last 12 months summary (by month)
            $monthlyData = [];
            for ($month = 1; $month <= 12; $month++) {
                $thisMonthIncomeSum = ExpenseCalculation::where('types', 'INCOME')
                    ->whereMonth('date', $month)
                    ->whereYear('date', $currentYear)
                    ->sum('amount');

                $thisMonthSalaryIncomeSum = ExpenseCalculation::where('types', 'INCOME')
                    ->where('category_id', 1)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $currentYear)
                    ->sum('amount');

                $thisMonthExpenseSum = ExpenseCalculation::where('types', 'EXPENSE')
                    ->whereMonth('date', $month)
                    ->whereYear('date', $currentYear)
                    ->groupBy('category_id')
                    ->select(DB::raw('SUM(amount) as totalExpense'))
                    ->get()
                    ->sum('totalExpense');

                $thisMonthneeds = ExpenseCalculation::where('rules', 'NEEDS')
                    ->whereMonth('date', $month)
                    ->whereYear('date', $currentYear)
                    ->sum('amount');

                $thisMonthwants = ExpenseCalculation::where('rules', 'WANTS')
                    ->whereMonth('date', $month)
                    ->whereYear('date', $currentYear)
                    ->sum('amount');

                $thisMonthsavings = ExpenseCalculation::where('rules', 'SAVINGS')
                    ->whereMonth('date', $month)
                    ->whereYear('date', $currentYear)
                    ->sum('amount');

                $monthlyData[$month] = [
                    'income' => (float) $thisMonthIncomeSum,
                    'needs' => (float) ($thisMonthSalaryIncomeSum * 0.5),
                    'wants' => (float) ($thisMonthSalaryIncomeSum * 0.3),
                    'savings' => (float) ($thisMonthSalaryIncomeSum * 0.2),
                    'expense' => (float) $thisMonthExpenseSum,
                    'thisMonthneeds' => (float) $thisMonthneeds,
                    'thisMonthwants' => (float) $thisMonthwants,
                    'thisMonthsavings' => (float) $thisMonthsavings,
                ];
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

            $view->with(compact('monthlyData', 'thisMonthIncome', 'thisMonthExpense', 'thisMonthneeds', 'thisMonthwants', 'thisMonthsavings', 'thisYearIncome', 'thisYearExpense', 'categoryMap', 'currentMonth', 'currentYear', 'thisMonthtotalIncome', 'thisYeartotalIncome', 'insights', 'startDate', 'endDate', 'minDataDate'));
        });
    }
}
