<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $cashierRole = Role::query()->where('name', 'CASHIER')->where('guard_name', 'web')->first();
        if (! $cashierRole) {
            return;
        }

        $names = PermissionCatalog::cashierDefaultPermissions();
        if ($names === []) {
            $cashierRole->syncPermissions([]);

            return;
        }

        $cashierRole->syncPermissions(
            Permission::query()->whereIn('name', $names)->where('guard_name', 'web')->get()
        );
    }

    public function down(): void
    {
        //
    }
};
