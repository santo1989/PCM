<?php

namespace App\Console\Commands;

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\FinancialAnalysisDashboardController;
use App\Http\Controllers\HandCashController;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/**
 * Read-only: runs every existing report for 3 fixed periods and saves (or, with --check,
 * compares against) its numeric output, to prove analytics work never changes old reports.
 */
class ReportBaselines extends Command
{
    protected $signature = 'analytics:baselines {--check : Compare with the saved file instead of overwriting it}';

    protected $description = 'Capture or check the numeric output of the existing reports (read-only)';

    private const FILE = 'tests/fixtures/report_baselines.json';

    private const PERIODS = ['2024-01' => ['2024-01-01', '2024-01-31'], '2025-06' => ['2025-06-01', '2025-06-30'], '2025' => ['2025-01-01', '2025-12-31']];

    private const REPORTS = [
        'Yearly_report' => [HandCashController::class, 'Yearly_report'],
        'Monthly_report' => [HandCashController::class, 'Monthly_report'],
        'Monthly_invest' => [HandCashController::class, 'Monthly_invest'],
        'Budge_Projection' => [HandCashController::class, 'Budge_Projection'],
        'interactive.summary' => [HandCashController::class, 'interactiveDashboardSummary'],
        'interactive.monthly-trend' => [HandCashController::class, 'interactiveDashboardMonthlyTrend'],
        'interactive.category-breakdown' => [HandCashController::class, 'interactiveDashboardCategoryBreakdown'],
        'interactive.savings-loans' => [HandCashController::class, 'interactiveDashboardSavingsLoans'],
        'interactive.top-categories' => [HandCashController::class, 'interactiveDashboardTopCategories'],
        'interactive.running-balance' => [HandCashController::class, 'interactiveDashboardRunningBalance'],
        'financial_analysis.data' => [FinancialAnalysisDashboardController::class, 'data'],
        'cost_optimisation.data' => [AnalyticsController::class, 'costOptimisationData'],
        'predictive_budget' => [AnalyticsController::class, 'predictiveBudget'],
    ];

    // Their output also depends on today's date (monthly-trend: a rolling 12-month window), so drift there is expected.
    private const DATE_DEPENDENT = ['Budge_Projection', 'cost_optimisation.data', 'predictive_budget', 'interactive.monthly-trend'];

    public function handle()
    {
        Auth::setUser(User::orderBy('id')->first());
        $result = ['_meta' => ['captured_at' => now()->toDateTimeString(), 'note' => 'Numeric output of each report; lists collapsed to per-field sums. ' . implode(', ', self::DATE_DEPENDENT) . ' also depend on today.']];

        foreach (self::REPORTS as $name => [$class, $method]) {
            foreach (self::PERIODS as $label => [$start, $end]) {
                app()->instance('request', Request::create('/', 'GET', ['start_date' => $start, 'end_date' => $end, 'period' => 'custom']));
                try {
                    $response = app()->call([app($class), $method]);
                    $data = $response instanceof View ? $response->getData() : json_decode($response->getContent(), true);
                    $result[$name][$label] = $this->numbers($data);
                } catch (Throwable $e) {
                    $result[$name][$label] = ['error' => get_class($e) . ': ' . $e->getMessage()];
                }
            }
        }

        if (!$this->option('check')) {
            @mkdir(base_path('tests/fixtures'));
            file_put_contents(base_path(self::FILE), json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info('Saved ' . self::FILE);
            return self::SUCCESS;
        }

        $saved = json_decode(file_get_contents(base_path(self::FILE)), true);
        $drift = 0;
        foreach (self::REPORTS as $name => $_) {
            foreach (self::PERIODS as $label => $_p) {
                $old = $saved[$name][$label] ?? [];
                $new = $result[$name][$label];
                $diff = [];
                foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $k) {
                    if (abs(($old[$k] ?? 0) - ($new[$k] ?? 0)) > 0.005) {
                        $diff[] = "$k: " . ($old[$k] ?? '-') . ' -> ' . ($new[$k] ?? '-');
                    }
                }
                $expected = in_array($name, self::DATE_DEPENDENT, true);
                $drift += $diff && !$expected ? 1 : 0;
                $this->line(sprintf('%s %-32s %-8s %s', $diff ? ($expected ? 'DATE' : 'DRIFT') : 'SAME ', $name, $label, $diff ? count($diff) . ' changed, e.g. ' . implode('; ', array_slice($diff, 0, 2)) : ''));
            }
        }
        $this->line($drift ? "\n$drift report/period(s) DRIFTED" : "\nNo drift in date-independent reports");
        if ($drift) {
            // Balances and "current" figures ignore the date filter, so new or edited entries move them too.
            $since = $saved['_meta']['captured_at'];
            $changed = DB::table('expense_calculations')->where('updated_at', '>', $since)->count() + DB::table('hand_cashes')->where('updated_at', '>', $since)->count();
            $this->line($changed
                ? "$changed ledger/hand-cash rows were added or edited since the baselines ($since): check whether they explain the drift, then re-save with `php artisan analytics:baselines`."
                : "No entries changed since the baselines ($since), so the drift comes from code.");
        }
        return $drift ? self::FAILURE : self::SUCCESS;
    }

    /** Numeric leaves only; lists collapse to per-field sums plus a row count. AI/text keys are skipped. */
    private function numbers($v, string $path = '', array &$out = []): array
    {
        if ($v instanceof \Illuminate\Contracts\Support\Arrayable) {
            $v = $v->toArray();
        }
        if (is_object($v)) {
            $v = (array) $v;
        }
        if (is_array($v)) {
            $isList = array_is_list($v);
            if ($isList && $path !== '') {
                $out["$path.#rows"] = count($v);
            }
            foreach ($v as $k => $x) {
                if (!preg_match('/ai|insight|outlook|recommend|generated|suggestion/i', (string) $k)) {
                    $this->numbers($x, $isList ? "{$path}[*]" : ($path === '' ? $k : "$path.$k"), $out);
                }
            }
        } elseif (is_int($v) || is_float($v) || (is_string($v) && preg_match('/^-?\d+(\.\d+)?$/', $v) && strlen($v) < 16)) {
            $out[$path] = round(($out[$path] ?? 0) + (float) $v, 2);
        }
        return $out;
    }
}
