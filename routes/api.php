<?php

use App\Http\Controllers\AnalyticController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::prefix('1.0.0')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api')->name('logout');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('auth:api')->name('refresh');
    Route::post('/me', [AuthController::class, 'me'])->middleware('auth:api')->name('me');
    Route::middleware(['auth:api'])->group(function () {
        // Route::get('/user', fn() => Auth::user());
        // Route::middleware(['role:admin'])->group(function () {
        //     Route::get('/admin-only', fn() => ['message' => 'Hello Admin']);
        // });
        Route::apiResource('/products', ProductController::class);
        Route::apiResource('/transactions', TransactionController::class);
        Route::prefix('analytics')->controller(AnalyticController::class)->group(function () {
            Route::get('/sales-overview', 'salesOverview');
            Route::get('/sales/daily', 'salesDaily');
            Route::get('/sales/weekly', 'salesWeekly');
            Route::get('/sales/monthly', 'salesMonthly');
            Route::get('/sales/by-range', 'salesByRange');
            Route::get('/products/top-selling', 'topSellingProducts');
        });
    });
});
