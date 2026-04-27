<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Register;
use App\Models\StockBalance;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = [
            'dashboard.view',
            'registers.view', 'registers.manage',
            'stock_locations.view', 'stock_locations.manage',
            'stock.reload',
            'stock.movements.view',
            'stock.transfers.view', 'stock.transfers.manage',
            'users.view', 'users.manage',
            'roles.view', 'roles.manage',
            'products.view', 'products.manage',
            'customers.view', 'customers.manage',
            'sales.view',
            'purchases.view', 'purchases.manage',
            'reversals.view', 'reversals.manage',
        ];
        $permissionModels = collect();
        foreach ($permissions as $permissionName) {
            $permissionModels->push(Permission::findOrCreate($permissionName, 'web'));
        }

        $adminRole = Role::findOrCreate('ADMIN', 'web');
        $managerRole = Role::findOrCreate('MANAGER', 'web');
        $cashierRole = Role::findOrCreate('CASHIER', 'web');
        $adminRole->syncPermissions($permissionModels);
        $managerPermissions = [
            'dashboard.view',
            'registers.view', 'registers.manage',
            'stock_locations.view', 'stock_locations.manage',
            'stock.reload',
            'stock.movements.view',
            'stock.transfers.view', 'stock.transfers.manage',
            'users.view', 'users.manage',
            'roles.view', 'roles.manage',
            'products.view', 'products.manage',
            'customers.view', 'customers.manage',
            'sales.view',
            'purchases.view', 'purchases.manage',
            'reversals.view', 'reversals.manage',
        ];
        $managerRole->syncPermissions(
            Permission::query()->whereIn('name', $managerPermissions)->where('guard_name', 'web')->get()
        );

        $cashierPermissions = [
            'dashboard.view',
            'sales.view',
            'customers.view',
            'products.view',
            'stock.reload',
            'stock.movements.view',
            'users.view',
        ];
        $cashierRole->syncPermissions(
            Permission::query()->whereIn('name', $cashierPermissions)->where('guard_name', 'web')->get()
        );

        $register = Register::query()->firstOrCreate(
            ['code' => 'CX-01'],
            ['name' => 'Caixa 01', 'is_active' => true]
        );

        $sourceLocation = StockLocation::query()->firstOrCreate([
            'code' => 'LOC-CX01',
        ], [
            'register_id' => $register->id,
            'name' => 'Loja - Caixa 01',
            'type' => 'STORE_FLOOR',
            'is_saleable' => true,
            'is_active' => true,
        ]);

        $user = User::query()->updateOrCreate([
            'username' => 'operador',
        ], [
            'name' => 'Operador 01',
            'email' => 'operador@retailpro.local',
            'password' => bcrypt('123456'),
            'role' => 'CASHIER',
            'caixa_atribuido' => 'Caixa 01',
            'register_id' => $register->id,
            'source_location_id' => $sourceLocation->id,
            'is_active' => true,
        ]);
        $user->syncRoles(['CASHIER']);

        $admin = User::query()->updateOrCreate([
            'username' => 'admin',
        ], [
            'name' => 'Administrador',
            'email' => 'admin@retailpro.local',
            'password' => bcrypt('admin123456'),
            'role' => 'ADMIN',
            'caixa_atribuido' => null,
            'register_id' => $register->id,
            'source_location_id' => $sourceLocation->id,
            'is_active' => true,
        ]);
        $admin->syncRoles(['ADMIN']);

        Customer::query()->firstOrCreate(
            ['nome' => 'Cliente Geral'],
            ['telefone' => '000000000', 'email' => 'cliente@demo.co.mz', 'nuit' => '400000099', 'is_active' => true]
        );

        $product = Product::query()->firstOrCreate([
            'nome' => 'Pão francês',
        ], [
            'codigo_barras' => '5601000000012',
            'categoria' => 'Padaria',
            'preco_compra' => 6,
            'preco_venda' => 8,
            'iva_tipo' => 'ISENTO',
            'iva_valor' => 0,
            'iva_percentual' => 0,
            'stock' => 120,
            'is_active' => true,
        ]);

        StockBalance::query()->updateOrCreate(
            [
                'location_id' => $sourceLocation->id,
                'product_id' => $product->id,
            ],
            [
                'quantity' => 120,
                'min_stock' => 10,
                'max_stock' => 500,
            ]
        );

        Register::query()->firstOrCreate(
            ['code' => 'CX-02'],
            ['name' => 'Caixa 02', 'is_active' => true]
        );

        StockLocation::query()->firstOrCreate([
            'code' => 'LOC-ARM-CENTRAL',
        ], [
            'register_id' => null,
            'name' => 'Armazém Central',
            'type' => 'WAREHOUSE',
            'is_saleable' => false,
            'is_active' => true,
        ]);
    }
}
