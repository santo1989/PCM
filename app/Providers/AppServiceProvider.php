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
        // Move heavy DB queries out of Blade by composing data for the admin layout
        View::composer('layouts.Admin', function ($view) {
            $currentYear = request('year') ?? date('Y');
            $currentMonth = request('month') ?? date('m');

            // Last 12 months summary (by month)
            $monthlyData = [];
            for ($month = 1; $month <= 12; $month++) {
                $thisMonthIncomeSum = ExpenseCalculation::where('types', 'income')
                    ->whereMonth('date', $month)
                    ->whereYear('date', $currentYear)
                    ->sum('amount');

                $thisMonthSalaryIncomeSum = ExpenseCalculation::where('types', 'income')
                    ->where('category_id', 1)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $currentYear)
                    ->sum('amount');

                $thisMonthExpenseSum = ExpenseCalculation::where('types', 'expense')
                    ->whereMonth('date', $month)
                    ->whereYear('date', $currentYear)
                    ->groupBy('category_id')
                    ->select(DB::raw('SUM(amount) as totalExpense'))
                    ->get()
                    ->sum('totalExpense');

                $thisMonthneeds = ExpenseCalculation::where('rules', 'needs')
                    ->whereMonth('date', $month)
                    ->whereYear('date', $currentYear)
                    ->sum('amount');

                $thisMonthwants = ExpenseCalculation::where('rules', 'wants')
                    ->whereMonth('date', $month)
                    ->whereYear('date', $currentYear)
                    ->sum('amount');

                $thisMonthsavings = ExpenseCalculation::where('rules', 'savings')
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

            // Current month/year selections
            $currentMonth = (int) $currentMonth;
            $currentYear = (int) $currentYear;

            // Exports tab data
            $thisMonthIncome = ExpenseCalculation::where('types', 'income')
                ->whereMonth('date', $currentMonth)
                ->whereYear('date', $currentYear)
                ->get();

            $thisMonthExpense = ExpenseCalculation::where('types', 'expense')
                ->whereMonth('date', $currentMonth)
                ->whereYear('date', $currentYear)
                ->groupBy('category_id')
                ->select('category_id', DB::raw('SUM(amount) as totalExpense'))
                ->orderBy('totalExpense', 'desc')
                ->get();

            $thisMonthneeds = ExpenseCalculation::Where('rules', 'needs')
                ->whereMonth('date', $currentMonth)
                ->whereYear('date', $currentYear)
                ->sum('amount');

            $thisMonthwants = ExpenseCalculation::Where('rules', 'wants')
                ->whereMonth('date', $currentMonth)
                ->whereYear('date', $currentYear)
                ->sum('amount');

            $thisMonthsavings = ExpenseCalculation::Where('rules', 'savings')
                ->whereMonth('date', $currentMonth)
                ->whereYear('date', $currentYear)
                ->sum('amount');

            $thisYearIncome = ExpenseCalculation::Where('types', 'income')
                ->whereYear('date', $currentYear)
                ->get();

            $thisYearExpense = ExpenseCalculation::Where('types', 'expense')
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

            $view->with(compact('monthlyData', 'thisMonthIncome', 'thisMonthExpense', 'thisMonthneeds', 'thisMonthwants', 'thisMonthsavings', 'thisYearIncome', 'thisYearExpense', 'categoryMap', 'currentMonth', 'currentYear'));
        });
    }
}
