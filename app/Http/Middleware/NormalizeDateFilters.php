<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Cleans the date-range filters every page reads, in one place:
 *  - empty or invalid dates are dropped, so pages fall back to their defaults
 *    instead of crashing in Carbon::parse() ("start_date=abc" was a 500);
 *  - a range picked backwards (end before start) is swapped instead of
 *    returning nothing.
 */
class NormalizeDateFilters
{
    private const PAIRS = [
        ['start_date', 'end_date'],
        ['entry_date_start', 'entry_date_end'],
        ['handCashes_date_start', 'handCashes_date_end'],
        ['balance_date_start', 'balance_date_end'],
    ];

    public function handle(Request $request, Closure $next)
    {
        foreach (self::PAIRS as [$startKey, $endKey]) {
            foreach ([$startKey, $endKey] as $key) {
                if ($request->query->has($key) && !self::isDate($request->query($key))) {
                    $request->query->remove($key);
                }
            }

            $start = $request->query($startKey);
            $end = $request->query($endKey);
            if ($start && $end && $start > $end) {
                $request->query->set($startKey, $end);
                $request->query->set($endKey, $start);
            }
        }

        return $next($request);
    }

    private static function isDate($value): bool
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $value));

        return checkdate($m, $d, $y);
    }
}
