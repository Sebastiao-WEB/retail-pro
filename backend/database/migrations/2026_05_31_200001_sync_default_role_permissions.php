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

        $adminRole = Role::query()->where('name', 'ADMIN')->where('guard_name', 'web')->first();
        $managerRole = Role::query()->where('name', 'MANAGER')->where('guard_name', 'web')->first();
        $cashierRole = Role::query()->where('name', 'CASHIER')->where('guard_name', 'web')->first();

        if ($adminRole) {
            $adminRole->syncPermissions(
                Permission::query()->whereIn('name', PermissionCatalog::allNames())->where('guard_name', 'web')->get()
            );
        }

        if ($managerRole) {
            $managerRole->syncPermissions(
                Permission::query()->whereIn('name', PermissionCatalog::managerDefaultPermissions())->where('guard_name', 'web')->get()
            );
        }

        if ($cashierRole) {
            $cashierRole->syncPermissions(
                Permission::query()->whereIn('name', PermissionCatalog::cashierDefaultPermissions())->where('guard_name', 'web')->get()
            );
        }
    }

    public function down(): void
    {
        //
    }
};
