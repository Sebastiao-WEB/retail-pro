<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CompanyProfile;
use App\Models\Product;
use App\Models\Register;
use App\Models\StockBalance;
use App\Models\StockLocation;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        PermissionCatalog::sync();

        $adminRole = Role::findOrCreate('ADMIN', 'web');
        $managerRole = Role::findOrCreate('MANAGER', 'web');
        $cashierRole = Role::findOrCreate('CASHIER', 'web');

        $adminRole->syncPermissions(
            Permission::query()->whereIn('name', PermissionCatalog::allNames())->where('guard_name', 'web')->get()
        );
        $managerRole->syncPermissions(
            Permission::query()->whereIn('name', PermissionCatalog::managerDefaultPermissions())->where('guard_name', 'web')->get()
        );
        $cashierRole->syncPermissions(
            Permission::query()->whereIn('name', PermissionCatalog::cashierDefaultPermissions())->where('guard_name', 'web')->get()
        );

        $register = Register::query()->firstOrCreate(
            ['code' => 'CX-01'],
            ['name' => 'Caixa 01', 'is_active' => true]
        );

        $sourceLocation = StockLocation::query()->firstOrCreate([
            'code' => 'LOC-CX01',
        ], [
            'name' => 'Loja - Caixa 01',
            'type' => 'STORE_FLOOR',
            'is_saleable' => true,
            'is_active' => true,
        ]);
        $sourceLocation->registers()->syncWithoutDetaching([$register->id]);

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
        $register2 = Register::query()->firstOrCreate(
            ['code' => 'CX-02'],
            ['name' => 'Caixa 02', 'is_active' => true]
        );

        $sourceLocation2 = StockLocation::query()->firstOrCreate([
            'code' => 'LOC-CX02',
        ], [
            'name' => 'Loja - Caixa 02',
            'type' => 'STORE_FLOOR',
            'is_saleable' => true,
            'is_active' => true,
        ]);
        $sourceLocation2->registers()->syncWithoutDetaching([$register2->id]);

        $user->syncRoles(['CASHIER']);
        $user->syncAssignedRegisters([$register->id, $register2->id]);
        $user->save();

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

        CompanyProfile::query()->firstOrCreate([], [
            'name' => 'Empresa Demo Lda',
            'nif' => '400000099',
            'email' => 'geral@empresa.co.mz',
            'phone' => '+258 21 000 000',
            'address' => 'Av. 25 de Setembro, 420, Maputo, Moçambique',
            'bank' => 'BCI — Banco Comercial e de Investimentos',
            'iban' => 'MZ59 0000 0000 1234 5678 901',
        ]);

        $product = Product::query()->firstOrCreate([
            'nome' => 'Pão francês 200g',
        ], [
            'codigo_barras' => '0000000000000',
            'categoria' => 'Padaria',
            'preco_compra' => 0,
            'preco_venda' => 0,
            'iva_tipo' => 'ISENTO',
            'iva_valor' => 0,
            'iva_percentual' => 0,
            'stock' => 0,
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

        StockBalance::query()->updateOrCreate(
            [
                'location_id' => $sourceLocation2->id,
                'product_id' => $product->id,
            ],
            [
                'quantity' => 80,
                'min_stock' => 10,
                'max_stock' => 500,
            ]
        );

        \App\Support\ProductStockDisplay::sincronizarStockGlobal($product->id);

        StockLocation::query()->firstOrCreate([
            'code' => 'LOC-ARM-CENTRAL',
        ], [
            'name' => 'Armazém Central',
            'type' => 'WAREHOUSE',
            'is_saleable' => false,
            'is_active' => true,
        ]);
    }
}
