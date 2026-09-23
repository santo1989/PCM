<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cheap change-detection endpoint polled by the UI so listings refresh when data changes
 * (from another tab, device or user). Returns a short hash, never row data.
 */
class LiveController extends Controller
{
    public function signature()
    {
        // Cached for 2s so many open tabs cost at most one aggregate query burst per window.
        $sig = Cache::remember('live:signature', 2, function () {
            $parts = [];
            foreach (['expense_calculations', 'hand_cashes', 'categories', 'users', 'roles'] as $table) {
                $row = DB::table($table)->selectRaw('COUNT(*) as c, MAX(updated_at) as m, MAX(id) as i')->first();
                $parts[$table] = [$row->c, $row->m, $row->i];
            }

            return md5(json_encode($parts));
        });

        return response()->json(['sig' => $sig])->header('Cache-Control', 'no-store');
    }
}
