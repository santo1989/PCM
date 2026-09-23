<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RememberIndexUrl;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Redirect to the listing the user was last viewing (filters and page number intact),
     * falling back to the plain index route.
     */
    /** Page size from ?per_page= (whitelisted), so every listing paginates consistently. */
    protected function perPage(int $default = 25): int
    {
        $n = (int) request("per_page", $default);

        return in_array($n, [10, 25, 50, 100], true) ? $n : $default;
    }

    protected function redirectToIndex(string $routeName)
    {
        return redirect()->to(RememberIndexUrl::lastUrl($routeName));
    }
}
