<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Remembers the last visited URL (with filters + page) of every "*.index" listing so that
 * create/update/delete actions can send the user back to exactly where they were.
 */
class RememberIndexUrl
{
    private const SESSION_KEY = 'last_index_urls';

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $request->hasSession() && $response->isSuccessful()) {
            $name = optional($request->route())->getName();
            if ($name && str_ends_with($name, '.index') && !$request->has('export_format')) {
                $urls = $request->session()->get(self::SESSION_KEY, []);
                $urls[$name] = $request->fullUrl();
                $request->session()->put(self::SESSION_KEY, $urls);
            }
        }

        return $response;
    }

    public static function lastUrl(string $routeName): string
    {
        $urls = session(self::SESSION_KEY, []);

        return $urls[$routeName] ?? route($routeName);
    }
}
