<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CashSessionController;
use App\Http\Controllers\Api\V1\CompanyProfileController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\RegisterController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SaleReversalRequestController;
use App\Http\Controllers\Api\V1\StockController;
use App\Http\Controllers\Api\V1\StockMovementController;
use App\Http\Controllers\Api\V1\StockLocationController;
use App\Http\Controllers\Api\V1\StockTransferController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');
    });

    Route::middleware('auth:api')->group(function () {
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
        Route::post('cash-sessions/{id}/close', [CashSessionController::class, 'close']);
        Route::get('cash-sessions/active', [CashSessionController::class, 'active']);
        Route::get('cash-sessions/{id}/movements', [CashSessionController::class, 'movements']);

        Route::get('purchases', [PurchaseController::class, 'index']);
        Route::post('purchases', [PurchaseController::class, 'store']);
        Route::put('purchases/{purchase}', [PurchaseController::class, 'update']);
        Route::delete('purchases/{purchase}', [PurchaseController::class, 'destroy']);

        Route::get('registers', [RegisterController::class, 'index']);
        Route::post('registers', [RegisterController::class, 'store']);
        Route::put('registers/{register}', [RegisterController::class, 'update']);

        Route::get('stock-locations', [StockLocationController::class, 'index']);
        Route::post('stock-locations', [StockLocationController::class, 'store']);
        Route::put('stock-locations/{stockLocation}', [StockLocationController::class, 'update']);

        Route::post('stock/reload', [StockController::class, 'reload']);
        Route::get('stock/movements', [StockMovementController::class, 'index']);
        Route::get('stock/transfers', [StockTransferController::class, 'index']);
        Route::post('stock/transfers', [StockTransferController::class, 'store']);

        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::patch('users/{user}/status', [UserController::class, 'updateStatus']);
    });
});
