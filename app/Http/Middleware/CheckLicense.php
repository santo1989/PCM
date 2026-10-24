<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;

class CheckLicense
{
    public function handle(Request $request, Closure $next)
    {
        if (config('license.enforce')) {
            $expired = Carbon::parse(config('license.expires_at'))->endOfDay()->isPast();
            if ($expired || !config('license.key')) {
                abort(402, 'Subscription expired or license key missing. Contact Northern Group IT.');
            }
        }

        return $next($request);
    }
}
