<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CashSessionController;
use App\Http\Controllers\Api\V1\CompanyProfileController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\RegisterController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SaleReversalRequestController;
use App\Http\Controllers\Api\V1\StockController;
use App\Http\Controllers\Api\V1\StockMovementController;
use App\Http\Controllers\Api\V1\StockLocationController;
use App\Http\Controllers\Api\V1\StockTransferController;
use App\Http\Controllers\Api\V1\TwoFactorSettingsController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('admin-login', [AuthController::class, 'adminLogin']);
        Route::post('two-factor-challenge', [AuthController::class, 'twoFactorChallenge'])
            ->middleware('throttle:5,1');
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');
    });

    Route::middleware('auth:api')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::put('auth/password', [AuthController::class, 'updatePassword']);
        Route::get('dashboard/summary', [DashboardController::class, 'summary']);
        Route::get('dashboard/recent-sales', [DashboardController::class, 'recentSales']);

        Route::prefix('auth/two-factor')->group(function () {
            Route::get('status', [TwoFactorSettingsController::class, 'status']);
            Route::post('enable', [TwoFactorSettingsController::class, 'enable']);
            Route::post('confirm', [TwoFactorSettingsController::class, 'confirm']);
            Route::delete('', [TwoFactorSettingsController::class, 'disable']);
            Route::get('qr-code', [TwoFactorSettingsController::class, 'qrCode']);
            Route::post('recovery-codes', [TwoFactorSettingsController::class, 'recoveryCodes']);
            Route::post('recovery-codes/regenerate', [TwoFactorSettingsController::class, 'regenerateRecoveryCodes']);
        });

        Route::get('company-profile', [CompanyProfileController::class, 'show']);
        Route::put('company-profile', [CompanyProfileController::class, 'update']);

        Route::get('products', [ProductController::class, 'index']);
        Route::post('products', [ProductController::class, 'store']);
        Route::get('products/{product}', [ProductController::class, 'show']);
        Route::put('products/{product}', [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);

        Route::get('customers', [CustomerController::class, 'index']);
        Route::post('customers', [CustomerController::class, 'store']);
        Route::get('customers/{customer}', [CustomerController::class, 'show']);
        Route::put('customers/{customer}', [CustomerController::class, 'update']);
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy']);

        Route::get('sales', [SaleController::class, 'index']);
        Route::post('sales', [SaleController::class, 'store']);
        Route::get('sales/{sale}', [SaleController::class, 'show']);

        Route::get('sale-reversal-requests', [SaleReversalRequestController::class, 'index']);
        Route::post('sale-reversal-requests', [SaleReversalRequestController::class, 'store']);
        Route::patch('sale-reversal-requests/{saleReversalRequest}', [SaleReversalRequestController::class, 'update']);

        Route::post('cash-sessions/open', [CashSessionController::class, 'open']);
        Route::get('cash-sessions', [CashSessionController::class, 'index']);
        Route::post('cash-sessions/{id}/close', [CashSessionController::class, 'close']);
        Route::get('cash-sessions/active', [CashSessionController::class, 'active']);
        Route::get('cash-sessions/{id}/movements', [CashSessionController::class, 'movements']);

        Route::get('registers', [RegisterController::class, 'index']);
        Route::post('registers', [RegisterController::class, 'store']);
        Route::put('registers/{register}', [RegisterController::class, 'update']);

        Route::get('stock-locations', [StockLocationController::class, 'index']);
        Route::post('stock-locations', [StockLocationController::class, 'store']);
        Route::put('stock-locations/{stockLocation}', [StockLocationController::class, 'update']);

        Route::post('stock/reload', [StockController::class, 'reload']);
        Route::post('stock/adjust', [StockController::class, 'adjust']);
        Route::get('stock/availability', [StockController::class, 'availability']);
        Route::get('stock/movements', [StockMovementController::class, 'index']);
        Route::get('stock/transfers', [StockTransferController::class, 'index']);
        Route::post('stock/transfers', [StockTransferController::class, 'store']);

        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::patch('users/{user}/status', [UserController::class, 'updateStatus']);
    });
});
