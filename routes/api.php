<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::get("/check", function () {
        return "Hello check";
    });
});

// Android stock app: read-only, never contacts DSE.
Route::prefix('mobile')->middleware(\App\Http\Middleware\CheckLicense::class)->group(function () {
    Route::post('/login', [\App\Http\Controllers\MobileStockController::class, 'login'])->middleware('throttle:mobile-login');
    Route::middleware(\App\Http\Middleware\MobileToken::class)->group(function () {
        Route::post('/logout', [\App\Http\Controllers\MobileStockController::class, 'logout']);
        Route::get('/status', [\App\Http\Controllers\MobileStockController::class, 'status']);
        Route::get('/portfolio', [\App\Http\Controllers\MobileStockController::class, 'portfolio']);
        Route::get('/analysis', [\App\Http\Controllers\MobileStockController::class, 'analysis']);
        Route::get('/analysis/{symbol}', [\App\Http\Controllers\MobileStockController::class, 'analysisDetail'])->where('symbol', '[A-Za-z0-9\-\(\)&]{2,20}');
    });
});
