<?php

use App\Http\Controllers\Admin\BalanceSheetPdfController;
use App\Http\Controllers\Admin\OperatorReportPdfController;
use App\Http\Controllers\Admin\ReversalReportPdfController;
use App\Http\Controllers\Admin\Web\BalanceSheetWebController;
use App\Http\Controllers\Admin\Web\CashSessionWebController;
use App\Http\Controllers\Admin\Web\CompanySettingsWebController;
use App\Http\Controllers\Admin\Web\CustomerWebController;
use App\Http\Controllers\Admin\Web\DashboardWebController;
use App\Http\Controllers\Admin\Web\OperatorReportWebController;
use App\Http\Controllers\Admin\Web\ProductWebController;
use App\Http\Controllers\Admin\Web\RegisterWebController;
use App\Http\Controllers\Admin\Web\ReversalWebController;
use App\Http\Controllers\Admin\Web\RolePermissionWebController;
use App\Http\Controllers\Admin\Web\SalesWebController;
use App\Http\Controllers\Admin\Web\SecuritySettingsWebController;
use App\Http\Controllers\Admin\Web\StockLocationWebController;
use App\Http\Controllers\Admin\Web\StockMovementWebController;
use App\Http\Controllers\Admin\Web\StockReloadWebController;
use App\Http\Controllers\Admin\Web\StockTransferWebController;
use App\Http\Controllers\Admin\Web\UserWebController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardWebController::class, 'index'])->middleware('permission:dashboard.view')->name('dashboard');

    Route::middleware('permission:products.view')->group(function () {
        Route::get('/produtos', [ProductWebController::class, 'index'])->name('products.index');
        Route::get('/produtos/{product}/editar', [ProductWebController::class, 'edit'])->middleware('permission:products.manage')->name('products.edit');
        Route::get('/produtos/{product}', [ProductWebController::class, 'show'])->middleware('permission:products.manage')->name('products.show');
        Route::post('/produtos', [ProductWebController::class, 'store'])->middleware('permission:products.manage')->name('products.store');
        Route::put('/produtos/{product}', [ProductWebController::class, 'update'])->middleware('permission:products.manage')->name('products.update');
    });

    Route::middleware('permission:customers.view')->group(function () {
        Route::get('/clientes', [CustomerWebController::class, 'index'])->name('customers.index');
        Route::get('/clientes/{customer}/editar', [CustomerWebController::class, 'edit'])->middleware('permission:customers.manage')->name('customers.edit');
        Route::get('/clientes/{customer}', [CustomerWebController::class, 'show'])->middleware('permission:customers.manage')->name('customers.show');
        Route::post('/clientes', [CustomerWebController::class, 'store'])->middleware('permission:customers.manage')->name('customers.store');
        Route::put('/clientes/{customer}', [CustomerWebController::class, 'update'])->middleware('permission:customers.manage')->name('customers.update');
        Route::delete('/clientes/{customer}', [CustomerWebController::class, 'destroy'])->middleware('permission:customers.manage')->name('customers.destroy');
    });

    Route::middleware('permission:sales.view')->group(function () {
        Route::get('/vendas', [SalesWebController::class, 'index'])->name('sales.index');
        Route::get('/vendas/export/csv', [SalesWebController::class, 'exportCsv'])->middleware('permission:sales.export')->name('sales.export');
        Route::get('/vendas/{sale}/detalhes', [SalesWebController::class, 'detail'])->name('sales.detail');
        Route::get('/vendas/{sale}', [SalesWebController::class, 'show'])->name('sales.show');
    });

    Route::middleware('permission:balance_sheets.view')->group(function () {
        Route::get('/balanco-patrimonial', [BalanceSheetWebController::class, 'index'])->name('balance-sheets.index');
        Route::get('/balanco-patrimonial/{balanceSheet}', [BalanceSheetWebController::class, 'show'])->name('balance-sheets.show');
        Route::post('/balanco-patrimonial', [BalanceSheetWebController::class, 'store'])->middleware('permission:balance_sheets.manage')->name('balance-sheets.store');
        Route::put('/balanco-patrimonial/{balanceSheet}', [BalanceSheetWebController::class, 'update'])->middleware('permission:balance_sheets.manage')->name('balance-sheets.update');
        Route::post('/balanco-patrimonial/{balanceSheet}/recalcular', [BalanceSheetWebController::class, 'recalculate'])->middleware('permission:balance_sheets.manage')->name('balance-sheets.recalculate');
        Route::post('/balanco-patrimonial/{balanceSheet}/finalizar', [BalanceSheetWebController::class, 'finalize'])->middleware('permission:balance_sheets.manage')->name('balance-sheets.finalize');
        Route::get('/balanco-patrimonial/{balanceSheet}/pdf', BalanceSheetPdfController::class)->name('balance-sheets.pdf');
    });

    Route::get('/relatorio-operadores', [OperatorReportWebController::class, 'index'])->middleware('permission:operator_reports.view')->name('operator-reports.index');
    Route::get('/relatorio-operadores/detalhes', [OperatorReportWebController::class, 'detail'])->middleware('permission:operator_reports.view')->name('operator-reports.detail');
    Route::get('/relatorio-operadores/pdf', OperatorReportPdfController::class)->middleware('permission:operator_reports.view')->name('operator-reports.pdf');

    Route::middleware('permission:cash_sessions.view')->group(function () {
        Route::get('/sessoes-caixa-activas', [CashSessionWebController::class, 'activeIndex'])->name('cash-sessions.active');
        Route::get('/historico-fechos-caixa', [CashSessionWebController::class, 'closedIndex'])->name('cash-sessions.closed');
        Route::get('/sessoes-caixa/{cashSession}/detalhes', [CashSessionWebController::class, 'detail'])->name('cash-sessions.detail');
        Route::get('/sessoes-caixa/{cashSession}', [CashSessionWebController::class, 'show'])->name('cash-sessions.show');
    });

    Route::middleware('permission:reversals.view')->group(function () {
        Route::get('/reversoes', [ReversalWebController::class, 'index'])->name('reversals.index');
        Route::post('/reversoes/{reversalRequest}/decidir', [ReversalWebController::class, 'decide'])->middleware('permission:reversals.manage')->name('reversals.decide');
        Route::get('/reversoes/pdf', ReversalReportPdfController::class)->name('reversals.pdf');
    });

    Route::middleware('permission:registers.view')->group(function () {
        Route::get('/caixas', [RegisterWebController::class, 'index'])->name('registers.index');
        Route::get('/caixas/{register}/editar', [RegisterWebController::class, 'edit'])->middleware('permission:registers.manage')->name('registers.edit');
        Route::get('/caixas/{register}', [RegisterWebController::class, 'show'])->middleware('permission:registers.manage')->name('registers.show');
        Route::post('/caixas', [RegisterWebController::class, 'store'])->middleware('permission:registers.manage')->name('registers.store');
        Route::put('/caixas/{register}', [RegisterWebController::class, 'update'])->middleware('permission:registers.manage')->name('registers.update');
        Route::delete('/caixas/{register}', [RegisterWebController::class, 'destroy'])->middleware('permission:registers.manage')->name('registers.destroy');
    });

    Route::middleware('permission:stock_locations.view')->group(function () {
        Route::get('/armazens-localizacoes', [StockLocationWebController::class, 'index'])->name('stock-locations.index');
        Route::get('/armazens-localizacoes/{stockLocation}/editar', [StockLocationWebController::class, 'edit'])->middleware('permission:stock_locations.manage')->name('stock-locations.edit');
        Route::get('/armazens-localizacoes/{stockLocation}/stock', [StockLocationWebController::class, 'stock'])->name('stock-locations.stock');
        Route::get('/armazens-localizacoes/{stockLocation}', [StockLocationWebController::class, 'show'])->middleware('permission:stock_locations.manage')->name('stock-locations.show');
        Route::post('/armazens-localizacoes', [StockLocationWebController::class, 'store'])->middleware('permission:stock_locations.manage')->name('stock-locations.store');
        Route::put('/armazens-localizacoes/{stockLocation}', [StockLocationWebController::class, 'update'])->middleware('permission:stock_locations.manage')->name('stock-locations.update');
        Route::delete('/armazens-localizacoes/{stockLocation}', [StockLocationWebController::class, 'destroy'])->middleware('permission:stock_locations.manage')->name('stock-locations.destroy');
    });

    Route::middleware('permission:stock.reload')->group(function () {
        Route::get('/historico-recargas', [StockReloadWebController::class, 'history'])->name('stock.reload.history');
        Route::get('/recarregar-stock/{product}/recarregar', [StockReloadWebController::class, 'reloadForm'])->name('stock.reload.form');
        Route::get('/recarregar-stock/{product}/ajustar', [StockReloadWebController::class, 'adjustForm'])->name('stock.reload.adjust.form');
        Route::post('/recarregar-stock/reload', [StockReloadWebController::class, 'reload'])->name('stock.reload.apply');
        Route::post('/recarregar-stock/adjust', [StockReloadWebController::class, 'adjust'])->name('stock.reload.adjust');
        Route::get('/recarregar-stock/saldo', [StockReloadWebController::class, 'balance'])->name('stock.reload.balance');
    });

    Route::get('/movimentos-stock', [StockMovementWebController::class, 'index'])->middleware('permission:stock.movements.view')->name('stock.movements');

    Route::middleware('permission:stock.transfers.view')->group(function () {
        Route::get('/transferencias-stock', [StockTransferWebController::class, 'index'])->name('stock.transfers');
        Route::get('/transferencias-stock/disponivel', [StockTransferWebController::class, 'available'])->name('stock.transfers.available');
        Route::post('/transferencias-stock', [StockTransferWebController::class, 'store'])->middleware('permission:stock.transfers.manage')->name('stock.transfers.store');
    });

    Route::middleware('permission:settings.view')->group(function () {
        Route::get('/configuracoes', [CompanySettingsWebController::class, 'index'])->name('settings.company');
        Route::put('/configuracoes', [CompanySettingsWebController::class, 'update'])->middleware('permission:settings.manage')->name('settings.company.update');
    });

    Route::get('/seguranca', [SecuritySettingsWebController::class, 'index'])->name('security.settings');

    Route::middleware('permission:users.view')->group(function () {
        Route::get('/utilizadores', [UserWebController::class, 'index'])->name('users.index');
        Route::get('/utilizadores/{user}/editar', [UserWebController::class, 'edit'])->middleware('permission:users.manage')->name('users.edit');
        Route::get('/utilizadores/{user}', [UserWebController::class, 'show'])->middleware('permission:users.manage')->name('users.show');
        Route::post('/utilizadores', [UserWebController::class, 'store'])->middleware('permission:users.manage')->name('users.store');
        Route::put('/utilizadores/{user}', [UserWebController::class, 'update'])->middleware('permission:users.manage')->name('users.update');
        Route::delete('/utilizadores/{user}', [UserWebController::class, 'destroy'])->middleware('permission:users.manage')->name('users.destroy');
    });

    Route::middleware('permission:roles.view')->group(function () {
        Route::get('/roles-permissoes', [RolePermissionWebController::class, 'index'])->name('roles.permissions');
        Route::put('/roles-permissoes/roles/{role}', [RolePermissionWebController::class, 'updateRole'])->middleware('permission:roles.manage')->name('roles.permissions.role');
        Route::put('/roles-permissoes/users/{user}', [RolePermissionWebController::class, 'updateUser'])->middleware('permission:roles.manage')->name('roles.permissions.user');
        Route::get('/roles-permissoes/users/{user}/permissions', [RolePermissionWebController::class, 'userPermissions'])->middleware('permission:roles.manage')->name('roles.permissions.user.data');
    });
});
