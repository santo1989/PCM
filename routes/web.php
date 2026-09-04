<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ExpenseCalculationController;
use App\Http\Controllers\FinancialAnalysisDashboardController;
use App\Http\Controllers\HandCashController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Models\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Interactive dashboard
Route::get('/interactive-dashboard', [App\Http\Controllers\HandCashController::class, 'interactiveDashboard'])->name('interactive.dashboard');
Route::get('/interactive-dashboard/data/summary', [App\Http\Controllers\HandCashController::class, 'interactiveDashboardSummary']);
Route::get('/interactive-dashboard/data/monthly-trend', [App\Http\Controllers\HandCashController::class, 'interactiveDashboardMonthlyTrend']);
Route::get('/interactive-dashboard/data/category-breakdown', [App\Http\Controllers\HandCashController::class, 'interactiveDashboardCategoryBreakdown']);
Route::get('/interactive-dashboard/data/savings-loans', [App\Http\Controllers\HandCashController::class, 'interactiveDashboardSavingsLoans']);
Route::get('/interactive-dashboard/data/top-categories', [App\Http\Controllers\HandCashController::class, 'interactiveDashboardTopCategories']);
Route::get('/interactive-dashboard/data/running-balance', [App\Http\Controllers\HandCashController::class, 'interactiveDashboardRunningBalance']);
Route::get('/interactive-dashboard/data/recent-transactions', [App\Http\Controllers\HandCashController::class, 'interactiveDashboardRecentTransactions']);
Route::get('/interactive-dashboard/data/ai-insights', [App\Http\Controllers\HandCashController::class, 'interactiveDashboardAI']);

Route::get('/filter', [ExpenseCalculationController::class, 'filter'])->name('expenseCalculations.filter');

// power_bi_report
Route::get('/power_bi_report', [HandCashController::class, 'power_bi_report'])->name('power_bi_report');


Route::middleware('auth')->group(function () {


    Route::get('/home', function () {
        return view('backend.home');
    })->name('home');

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

    //categories

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
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

    Route::get('/Yearly_report', [HandCashController::class, 'Yearly_report'])->name('Yearly_report');
    Route::get('/Monthly_report', [HandCashController::class, 'Monthly_report'])->name('Monthly_report');
    Route::get('/Monthly_invest', [HandCashController::class, 'Monthly_invest'])->name('Monthly_invest');

    Route::get('/Budge_Projection', [HandCashController::class, 'Budge_Projection'])->name('Budge_Projection');
    Route::get('/handCashes_transfer_create', [HandCashController::class, 'handCashes_transfer_create'])->name('handCashes_transfer_create');
    Route::post('/handCashes_transfer', [HandCashController::class, 'handCashes_transfer'])->name('handCashes_transfer');

    Route::post('/calculate-and-save-budget', [HandCashController::class, 'calculateAndSaveBudget'])->name('calculate_and_save_budget');

    // report Excel exports
    Route::get('/Yearly_report/export-excel', [HandCashController::class, 'exportYearlyReport'])->name('Yearly_report.export_excel');
    Route::get('/Monthly_report/export-excel', [HandCashController::class, 'exportMonthlyReport'])->name('Monthly_report.export_excel');
    Route::get('/Monthly_invest/export-excel', [HandCashController::class, 'exportMonthlyInvest'])->name('Monthly_invest.export_excel');
    Route::get('/Budge_Projection/export-excel', [HandCashController::class, 'exportBudgeProjection'])->name('Budge_Projection.export_excel');
    Route::get('/interactive-dashboard/export-excel', [HandCashController::class, 'exportInteractiveDashboard'])->name('interactive.dashboard.export_excel');

    // Financial analytics: AJAX data endpoints + new pages
    Route::get('/home/data/kpis', [AnalyticsController::class, 'homeKpis'])->name('home.data.kpis');
    Route::get('/Budge_Projection/data/forecast', [AnalyticsController::class, 'budgetForecast'])->name('Budge_Projection.data.forecast');
    Route::get('/Budge_Projection/data/compare-actual', [AnalyticsController::class, 'compareActual'])->name('Budge_Projection.data.compare_actual');
    Route::get('/interactive-dashboard/data/budget-alerts', [AnalyticsController::class, 'budgetAlerts'])->name('interactive.dashboard.data.budget_alerts');
    Route::get('/cost-optimisation', [AnalyticsController::class, 'costOptimisation'])->name('cost_optimisation');
    Route::get('/cost-optimisation/data', [AnalyticsController::class, 'costOptimisationData'])->name('cost_optimisation.data');
    Route::get('/predictive-budget', [AnalyticsController::class, 'predictiveBudget'])->name('predictive_budget');

    // Financial Analysis Dashboard: consolidated, drillable view over all the reports above
    Route::get('/financial-analysis', [FinancialAnalysisDashboardController::class, 'index'])->name('financial_analysis.index');
    Route::get('/financial-analysis/data', [FinancialAnalysisDashboardController::class, 'data'])->name('financial_analysis.data');
    Route::get('/financial-analysis/export', [FinancialAnalysisDashboardController::class, 'export'])->name('financial_analysis.export');
    Route::get('/financial-analysis/kpi/{type}', [FinancialAnalysisDashboardController::class, 'kpi'])->name('financial_analysis.kpi');
    Route::post('/financial-analysis/analyze', [FinancialAnalysisDashboardController::class, 'analyze'])->name('financial_analysis.analyze');
    Route::get('/financial-analysis/insights', [FinancialAnalysisDashboardController::class, 'insights'])->name('financial_analysis.insights');

    // Database backup
    Route::get('/backup/status', [BackupController::class, 'status'])->name('backup.status');
    Route::post('/backup/run', [BackupController::class, 'run'])->name('backup.run');
});



























Route::get('/read/{notification}', [NotificationController::class, 'read'])->name('notification.read');


require __DIR__ . '/auth.php';

//php artisan command

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

Route::get('/key =', function () {
    $key =  Artisan::call('key:generate');
    echo "key:generate<br>";
});

Route::get('/migrate', function () {
    $migrate = Artisan::call('migrate');
    echo "migration create<br>";
});

Route::get('/migrate-fresh', function () {
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
