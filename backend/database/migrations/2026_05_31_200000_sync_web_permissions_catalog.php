<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
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
    }

    public function down(): void
    {
        // Permissões em uso não são removidas para preservar histórico.
    }
};
