<?php

use App\Livewire\Admin\CustomersPage;
use App\Livewire\Admin\CompanySettingsPage;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\ProductsPage;
use App\Livewire\Admin\PurchasesPage;
use App\Livewire\Admin\RegistersPage;
use App\Livewire\Admin\ReversalsPage;
use App\Livewire\Admin\RolesPermissionsPage;
use App\Livewire\Admin\SalesPage;
use App\Livewire\Admin\StockLocationsPage;
use App\Livewire\Admin\StockMovementsPage;
use App\Livewire\Admin\StockReloadPage;
use App\Livewire\Admin\StockTransfersPage;
use App\Livewire\Admin\UsersPage;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'role:ADMIN|MANAGER'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('/produtos', ProductsPage::class)->middleware('permission:products.view')->name('products.index');
    Route::get('/clientes', CustomersPage::class)->middleware('permission:customers.view')->name('customers.index');
    Route::get('/vendas', SalesPage::class)->middleware('permission:sales.view')->name('sales.index');
    Route::get('/compras', PurchasesPage::class)->middleware('permission:purchases.view')->name('purchases.index');
    Route::get('/reversoes', ReversalsPage::class)->middleware('permission:reversals.view')->name('reversals.index');
    Route::get('/caixas', RegistersPage::class)->middleware('permission:registers.view')->name('registers.index');
    Route::get('/armazens-localizacoes', StockLocationsPage::class)->middleware('permission:stock_locations.view')->name('stock-locations.index');
    Route::get('/recarregar-stock', StockReloadPage::class)->middleware('permission:stock.reload')->name('stock.reload');
    Route::get('/movimentos-stock', StockMovementsPage::class)->middleware('permission:stock.movements.view')->name('stock.movements');
    Route::get('/transferencias-stock', StockTransfersPage::class)->middleware('permission:stock.transfers.view')->name('stock.transfers');
    Route::get('/configuracoes', CompanySettingsPage::class)->middleware('permission:dashboard.view')->name('settings.company');
    Route::get('/utilizadores', UsersPage::class)->middleware('permission:users.view')->name('users.index');
    Route::get('/roles-permissoes', RolesPermissionsPage::class)->middleware('permission:roles.view')->name('roles.permissions');
});
