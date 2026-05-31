<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\RolesPermissionsPage;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesPermissionsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PermissionCatalog::sync();
        Role::findOrCreate('ADMIN', 'web');
        Role::findOrCreate('MANAGER', 'web');
        Role::findOrCreate('CASHIER', 'web');
    }

    public function test_utilizador_com_roles_view_acede_a_pagina_em_modo_leitura(): void
    {
        $user = User::factory()->create(['role' => 'MANAGER']);
        $user->assignRole('MANAGER');
        $user->givePermissionTo('roles.view');

        Livewire::actingAs($user)
            ->test(RolesPermissionsPage::class)
            ->assertOk()
            ->assertViewHas('canManage', false);
    }

    public function test_utilizador_sem_roles_view_recebe_403(): void
    {
        $user = User::factory()->create(['role' => 'CASHIER']);
        $user->assignRole('CASHIER');

        Livewire::actingAs($user)
            ->test(RolesPermissionsPage::class)
            ->assertForbidden();
    }

    public function test_admin_role_mantem_permissoes_criticas(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $admin->assignRole('ADMIN');
        $admin->givePermissionTo(['roles.manage', 'roles.view']);

        Livewire::actingAs($admin)
            ->test(RolesPermissionsPage::class)
            ->set('selectedRole', 'ADMIN')
            ->set('rolePermissions', ['sales.view'])
            ->call('saveRolePermissions');

        $adminRole = Role::findByName('ADMIN', 'web');

        foreach (PermissionCatalog::adminLockedPermissions() as $permission) {
            $this->assertTrue($adminRole->hasPermissionTo($permission));
        }
    }

    public function test_nao_pode_alterar_proprios_acessos(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $admin->assignRole('ADMIN');
        $admin->givePermissionTo(['roles.manage', 'roles.view']);

        Livewire::actingAs($admin)
            ->test(RolesPermissionsPage::class)
            ->set('selectedUser', $admin->id)
            ->set('selectedUserRole', 'MANAGER')
            ->call('saveUserAccess')
            ->assertForbidden();
    }

    public function test_rota_roles_permissoes_exige_roles_view(): void
    {
        $user = User::factory()->create(['role' => 'CASHIER']);
        $user->assignRole('CASHIER');

        $this->actingAs($user)
            ->get(route('roles.permissions'))
            ->assertForbidden();
    }

    public function test_cashier_com_permissao_pode_aceder_vendas(): void
    {
        $user = User::factory()->create(['role' => 'CASHIER']);
        $user->assignRole('CASHIER');
        $user->givePermissionTo('sales.view');

        $this->actingAs($user)
            ->get(route('sales.index'))
            ->assertOk();
    }
}
