<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ExpenseCalculationController;
use App\Http\Controllers\FinancialAnalysisDashboardController;
use App\Http\Controllers\HandCashController;
use App\Http\Controllers\LiveController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RecurringTransactionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserController;
use App\Models\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {

    // Moved inside auth: these served financial data to anyone without logging in.
    // Interactive dashboard
    Route::get('/interactive-dashboard', [App\Http\Controllers\ReportController::class, 'interactiveDashboard'])->name('interactive.dashboard');
    Route::get('/interactive-dashboard/data/summary', [App\Http\Controllers\ReportController::class, 'interactiveDashboardSummary']);
    Route::get('/interactive-dashboard/data/monthly-trend', [App\Http\Controllers\ReportController::class, 'interactiveDashboardMonthlyTrend']);
    Route::get('/interactive-dashboard/data/category-breakdown', [App\Http\Controllers\ReportController::class, 'interactiveDashboardCategoryBreakdown']);
    Route::get('/interactive-dashboard/data/savings-loans', [App\Http\Controllers\ReportController::class, 'interactiveDashboardSavingsLoans']);
    Route::get('/interactive-dashboard/data/top-categories', [App\Http\Controllers\ReportController::class, 'interactiveDashboardTopCategories']);
    Route::get('/interactive-dashboard/data/running-balance', [App\Http\Controllers\ReportController::class, 'interactiveDashboardRunningBalance']);
    Route::get('/interactive-dashboard/data/recent-transactions', [App\Http\Controllers\ReportController::class, 'interactiveDashboardRecentTransactions']);
    Route::get('/interactive-dashboard/data/ai-insights', [App\Http\Controllers\ReportController::class, 'interactiveDashboardAI']);

    Route::get('/filter', [ExpenseCalculationController::class, 'filter'])->name('expenseCalculations.filter');

    // power_bi_report
    Route::get('/power_bi_report', [App\Http\Controllers\ReportController::class, 'power_bi_report'])->name('power_bi_report');


    Route::get('/home', function () {
        return view('backend.home');
    })->name('home');

    // Users and roles: admin only (any logged-in user could otherwise make themselves admin).
    Route::middleware('can:Admin')->group(function () {
    //role

    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('/roles/{role}', [RoleController::class, 'show'])->name('roles.show');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');


    //user

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::get(
        '/users/{user}/edit',
        [UserController::class, 'edit']
    )->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::get('/online-user', [UserController::class, 'onlineuserlist'])->name('online_user');

    Route::post('/users/{user}/users_active', [UserController::class, 'user_active'])->name('users.active');
    });

    //categories

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::post('/categories/budgets', [CategoryController::class, 'applyBudgets'])->name('categories.budgets.apply');
    Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    //handCashes

    Route::get('/handCashes', [HandCashController::class, 'index'])->name('handCashes.index');
    Route::get('/handCashes/create', [HandCashController::class, 'create'])->name('handCashes.create');
    Route::post('/handCashes', [HandCashController::class, 'store'])->name('handCashes.store');
    Route::get('/handCashes/{handCash}', [HandCashController::class, 'show'])->name('handCashes.show');
    Route::get('/handCashes/{handCash}/edit', [HandCashController::class, 'edit'])->name('handCashes.edit');
    Route::put('/handCashes/{handCash}', [HandCashController::class, 'update'])->name('handCashes.update');
    Route::delete('/handCashes/{handCash}', [HandCashController::class, 'destroy'])->name('handCashes.destroy');

    //cashes

    Route::get('/expenseCalculations', [ExpenseCalculationController::class, 'index'])->name('expenseCalculations.index');
    Route::get('/expenseCalculations/create', [ExpenseCalculationController::class, 'create'])->name('expenseCalculations.create');
    Route::post('/expenseCalculations', [ExpenseCalculationController::class, 'store'])->name('expenseCalculations.store');
    Route::get('/expenseCalculations/{cash}', [ExpenseCalculationController::class, 'show'])->name('expenseCalculations.show');
    Route::get('/expenseCalculations/{cash}/edit', [ExpenseCalculationController::class, 'edit'])->name('expenseCalculations.edit');
    Route::put('/expenseCalculations/{cash}', [ExpenseCalculationController::class, 'update'])->name('expenseCalculations.update');
    Route::delete('/expenseCalculations/{cash}', [ExpenseCalculationController::class, 'destroy'])->name('expenseCalculations.destroy');

    // Route::get('/expenseCalculations/export', [CashesController::class, 'Excelexport'])->name('cashes_export');

    //reports

    Route::get('/Yearly_report', [App\Http\Controllers\ReportController::class, 'Yearly_report'])->name('Yearly_report');
    Route::get('/income-expense-sheet', [App\Http\Controllers\IncomeExpenseSheetController::class, 'index'])->name('income_expense_sheet');

    // DSE stock portfolio
    Route::get('/stocks', [App\Http\Controllers\StockPortfolioController::class, 'index'])->name('stocks.index');
    Route::post('/stocks/trades', [App\Http\Controllers\StockPortfolioController::class, 'storeTrade'])->name('stocks.trades.store');
    Route::post('/stocks/cash', [App\Http\Controllers\StockPortfolioController::class, 'storeCash'])->name('stocks.cash.store');
    Route::post('/stocks/dividends', [App\Http\Controllers\StockPortfolioController::class, 'storeDividend'])->name('stocks.dividends.store');
    Route::post('/stocks/prices', [App\Http\Controllers\StockPortfolioController::class, 'updatePrices'])->name('stocks.prices.update');
    Route::post('/stocks/prices/fetch', [App\Http\Controllers\StockPortfolioController::class, 'fetchPrices'])->name('stocks.prices.fetch');

    // Freelance & Business (AUGUSTA IT)
    Route::get('/ventures', [App\Http\Controllers\VentureController::class, 'index'])->name('ventures.index');
    Route::post('/ventures', [App\Http\Controllers\VentureController::class, 'store'])->name('ventures.store');
    Route::post('/ventures/{expense}/client', [App\Http\Controllers\VentureController::class, 'setClient'])->name('ventures.client');
    Route::get('/ventures/invoices/create', [App\Http\Controllers\VentureBillingController::class, 'create'])->name('ventures.invoices.create');
    Route::post('/ventures/invoices', [App\Http\Controllers\VentureBillingController::class, 'store'])->name('ventures.invoices.store');
    Route::get('/ventures/invoices/{invoice}', [App\Http\Controllers\VentureBillingController::class, 'show'])->name('ventures.invoices.show');
    Route::get('/ventures/invoices/{invoice}/edit', [App\Http\Controllers\VentureBillingController::class, 'edit'])->name('ventures.invoices.edit');
    Route::put('/ventures/invoices/{invoice}', [App\Http\Controllers\VentureBillingController::class, 'update'])->name('ventures.invoices.update');
    Route::post('/ventures/invoices/{invoice}/pay', [App\Http\Controllers\VentureBillingController::class, 'pay'])->name('ventures.invoices.pay');
    Route::post('/ventures/schedules', [App\Http\Controllers\VentureBillingController::class, 'storeSchedule'])->name('ventures.schedules.store');
    Route::put('/ventures/schedules/{schedule}', [App\Http\Controllers\VentureBillingController::class, 'updateSchedule'])->name('ventures.schedules.update');
    Route::post('/ventures/letterhead', [App\Http\Controllers\VentureBillingController::class, 'profile'])->name('ventures.letterhead');
    Route::get('/Monthly_report', [App\Http\Controllers\ReportController::class, 'Monthly_report'])->name('Monthly_report');
    Route::get('/Monthly_invest', [App\Http\Controllers\ReportController::class, 'Monthly_invest'])->name('Monthly_invest');

    Route::get('/Budge_Projection', [App\Http\Controllers\ReportController::class, 'Budge_Projection'])->name('Budge_Projection');
    Route::get('/handCashes_transfer_create', [HandCashController::class, 'handCashes_transfer_create'])->name('handCashes_transfer_create');
    Route::post('/handCashes_transfer', [HandCashController::class, 'handCashes_transfer'])->name('handCashes_transfer');

    Route::post('/calculate-and-save-budget', [App\Http\Controllers\ReportController::class, 'calculateAndSaveBudget'])->name('calculate_and_save_budget');

    // report Excel exports
    Route::get('/Yearly_report/export-excel', [App\Http\Controllers\ReportController::class, 'exportYearlyReport'])->name('Yearly_report.export_excel');
    Route::get('/Monthly_report/export-excel', [App\Http\Controllers\ReportController::class, 'exportMonthlyReport'])->name('Monthly_report.export_excel');
    Route::get('/Monthly_invest/export-excel', [App\Http\Controllers\ReportController::class, 'exportMonthlyInvest'])->name('Monthly_invest.export_excel');
    Route::get('/Budge_Projection/export-excel', [App\Http\Controllers\ReportController::class, 'exportBudgeProjection'])->name('Budge_Projection.export_excel');
    Route::get('/interactive-dashboard/export-excel', [App\Http\Controllers\ReportController::class, 'exportInteractiveDashboard'])->name('interactive.dashboard.export_excel');

    // Financial analytics: AJAX data endpoints + new pages
    Route::get('/home/data/kpis', [AnalyticsController::class, 'homeKpis'])->name('home.data.kpis');
    Route::get('/Budge_Projection/data/forecast', [AnalyticsController::class, 'budgetForecast'])->name('Budge_Projection.data.forecast');
    Route::get('/Budge_Projection/data/compare-actual', [AnalyticsController::class, 'compareActual'])->name('Budge_Projection.data.compare_actual');
    Route::get('/Budge_Projection/data/budget-vs-actual', [AnalyticsController::class, 'budgetVsActual'])->name('Budge_Projection.data.budget_vs_actual');
    Route::get('/interactive-dashboard/data/budget-alerts', [AnalyticsController::class, 'budgetAlerts'])->name('interactive.dashboard.data.budget_alerts');
    Route::get('/cost-optimisation', [AnalyticsController::class, 'costOptimisation'])->name('cost_optimisation');
    Route::get('/cost-optimisation/data', [AnalyticsController::class, 'costOptimisationData'])->name('cost_optimisation.data');
    Route::get('/predictive-budget', [AnalyticsController::class, 'predictiveBudget'])->name('predictive_budget');
    Route::get('/analytics', [App\Http\Controllers\AnalyticsReportController::class, 'index'])->name('analytics.index');
    Route::get('/analytics/context', [App\Http\Controllers\AnalyticsReportController::class, 'context'])->name('analytics.context');
    Route::get('/analytics/export-excel', [App\Http\Controllers\AnalyticsReportController::class, 'export'])->name('analytics.export_excel');
    Route::post('/analytics/recompute', [App\Http\Controllers\AnalyticsReportController::class, 'recompute'])->middleware('can:Admin')->name('analytics.recompute');

    // Financial Analysis Dashboard: consolidated, drillable view over all the reports above
    Route::get('/financial-analysis', [FinancialAnalysisDashboardController::class, 'index'])->name('financial_analysis.index');
    Route::get('/financial-analysis/data', [FinancialAnalysisDashboardController::class, 'data'])->name('financial_analysis.data');
    Route::get('/financial-analysis/export', [FinancialAnalysisDashboardController::class, 'export'])->name('financial_analysis.export');
    Route::get('/financial-analysis/kpi/{type}', [FinancialAnalysisDashboardController::class, 'kpi'])->name('financial_analysis.kpi');
    Route::post('/financial-analysis/analyze', [FinancialAnalysisDashboardController::class, 'analyze'])->name('financial_analysis.analyze');
    Route::get('/financial-analysis/insights', [FinancialAnalysisDashboardController::class, 'insights'])->name('financial_analysis.insights');

    Route::get('/live/signature', [LiveController::class, 'signature'])->name('live.signature');

    // Database backup
    Route::get('/backup/status', [BackupController::class, 'status'])->name('backup.status');
    Route::post('/backup/run', [BackupController::class, 'run'])->name('backup.run');

    // Ctrl+K search palette
    Route::get('/search', SearchController::class)->name('search');

    // Recurring income/expenses
    Route::get('/recurring', [RecurringTransactionController::class, 'index'])->name('recurring.index');
    Route::post('/recurring', [RecurringTransactionController::class, 'store'])->name('recurring.store');
    Route::post('/recurring/run-now', [RecurringTransactionController::class, 'runNow'])->name('recurring.run_now');
    Route::get('/recurring/{recurring}/edit', [RecurringTransactionController::class, 'edit'])->name('recurring.edit');
    Route::put('/recurring/{recurring}', [RecurringTransactionController::class, 'update'])->name('recurring.update');
    Route::post('/recurring/{recurring}/toggle', [RecurringTransactionController::class, 'toggle'])->name('recurring.toggle');
    Route::delete('/recurring/{recurring}', [RecurringTransactionController::class, 'destroy'])->name('recurring.destroy');
});


Route::get('/read/{notification}', [NotificationController::class, 'read'])->middleware('auth')->name('notification.read');


require __DIR__ . '/auth.php';

// Maintenance shortcuts (php artisan ...) are admin-only: /migrate-fresh wipes the database.
Route::middleware(['auth', 'can:Admin'])->group(function () {
    Route::get('/foo', function () {
        Artisan::call('storage:link');
    });

    Route::get('/cleareverything', function () {
        $clearcache = Artisan::call('cache:clear');
        echo "Cache cleared<br>";

        $clearview = Artisan::call('view:clear');
        echo "View cleared<br>";

        $clearconfig = Artisan::call('config:cache');
        echo "Config cleared<br>";
    });

    // POST: a plain link or <img> must not be able to trigger these (CSRF only guards POST).
    Route::post('/key =', function () {
        $key =  Artisan::call('key:generate');
        echo "key:generate<br>";
    });

    Route::post('/migrate', function () {
        $migrate = Artisan::call('migrate');
        echo "migration create<br>";
    });

    Route::post('/migrate-fresh', function () {
        $fresh = Artisan::call('migrate:fresh --seed');
        echo "migrate:fresh --seed create<br>";
    });

    Route::get('/optimize', function () {
        $optimize = Artisan::call('optimize:clear');
        echo "optimize cleared<br>";
    });
    Route::get('/route-clear', function () {
        $route_clear = Artisan::call('route:clear');
        echo "route cleared<br>";
    });

    Route::get('/route-cache', function () {
        $route_cache = Artisan::call('route:cache');
        echo "route cache<br>";
    });

    Route::get('/updateapp', function () {
        $dump_autoload = Artisan::call('dump-autoload');
        echo 'dump-autoload complete';
    });
});
