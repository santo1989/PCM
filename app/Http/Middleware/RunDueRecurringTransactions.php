<?php

namespace App\Http\Middleware;

use App\Services\RecurringTransactionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Most installs have no cron, so the first signed-in page view each day also
 * creates any due recurring entries (the scheduler does the same when cron is
 * set up). Never blocks the request on failure.
 */
class RunDueRecurringTransactions
{
    public function handle(Request $request, Closure $next)
    {
        $key = 'recurring:ran:' . now()->toDateString();

        if ($request->user() && $request->isMethod('GET') && Cache::add($key, true, now()->endOfDay())) {
            try {
                app(RecurringTransactionService::class)->runDue();
            } catch (\Throwable $e) {
                // No same-day retry; tomorrow's run catches up every missed month anyway.
                Log::warning('Recurring transactions run failed: ' . $e->getMessage());
            }
        }

        return $next($request);
    }
}
