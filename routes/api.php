<?php

use App\Http\Controllers\AnalyticController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SalesReportController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\CheckIfUserIsBanned;
use Illuminate\Support\Facades\Route;

Route::prefix('1.0.0')->group(function () {
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::post('/register', 'register')->name('register');
        Route::post('/login', 'login')->name('login');
        Route::middleware(['auth:api'])->group(function () {
            Route::post('/refresh', 'refresh')->middleware('auth:api')->name('refresh');
            Route::post('/logout', 'logout')->middleware('auth:api')->name('logout');
            Route::post('/me', 'me')->middleware('auth:api')->name('me');
        });
    });

    Route::prefix('profile')->controller(UserController::class)->group(function () {
        Route::get('/', 'profile');
        Route::post('/update-username', 'updateUsername');
    });

    Route::middleware(['auth:api', CheckIfUserIsBanned::class])->group(function () {
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

        Route::get('/reports/sales', [SalesReportController::class, 'generate']);

        Route::prefix('user')->group(function () {
            Route::post('/upgrade-premium', [PaymentController::class, 'selfUpgrade']);
            Route::post('/validate-payment', [PaymentController::class, 'validatePayment']);
        });


        Route::middleware(['role:admin'])->group(function () {
            Route::post('/users/{id}/upgrade-premium', [UserController::class, 'upgradeToPremium']);
            Route::post('/users/{id}/downgrade', [UserController::class, 'downgrade']);
            Route::post('/users/{id}/ban', [UserController::class, 'ban']);
            Route::post('/users/{id}/lift-ban', [UserController::class, 'liftBan']);
        });
    });
});
