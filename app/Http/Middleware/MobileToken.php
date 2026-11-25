<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

/** Bearer token for the Android app: read-only, 90 days, for this request only (no session or cookies). */
class MobileToken
{
    public const ABILITY = 'mobile:read';
    public const DAYS = 90;

    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken() ? PersonalAccessToken::findToken($request->bearerToken()) : null;
        $user = $token ? $token->tokenable : null;
        if (!$user instanceof User || $token->abilities !== [self::ABILITY] || $token->created_at->addDays(self::DAYS)->isPast() || (int) $user->is_active !== 1) {
            return response()->json(['message' => 'Please sign in again.'], 401)->header('Cache-Control', 'no-store');
        }
        // stock_* rows are one owner's ledger (most have no user_id), so only the admin may read them.
        if (!$user->can('Admin')) {
            return response()->json(['message' => 'This account cannot use the stock app.'], 403)->header('Cache-Control', 'no-store');
        }

        Auth::setUser($user);
        $request->setUserResolver(fn() => $user);
        $request->attributes->set('mobile_token', $token);
        // The app polls every few seconds: one write a minute is enough.
        if (!$token->last_used_at || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now()])->save();
        }

        return $next($request)->header('Cache-Control', 'no-store');
    }
}
