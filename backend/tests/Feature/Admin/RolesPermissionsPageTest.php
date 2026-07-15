<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $user->givePermissionTo('roles.view');

        $this->actingAs($user)
            ->get(route('roles.permissions'))
            ->assertOk()
            ->assertSee(__('permissions.page_title'))
            ->assertDontSee('data-action="save-role-permissions"', false);
    }

    public function test_utilizador_sem_roles_view_recebe_403(): void
    {
        $user = User::factory()->create(['role' => 'MANAGER']);

        $this->actingAs($user)
            ->get(route('roles.permissions'))
            ->assertForbidden();
    }

    public function test_admin_role_mantem_permissoes_criticas(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $admin->assignRole('ADMIN');
        $admin->givePermissionTo(['roles.manage', 'roles.view']);

        $this->actingAs($admin)
            ->putJson(route('roles.permissions.role', ['role' => 'ADMIN']), [
                'rolePermissions' => ['sales.view'],
            ])
            ->assertOk();

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

        $this->actingAs($admin)
            ->putJson(route('roles.permissions.user', $admin), [
                'selectedUserRole' => 'MANAGER',
                'userDirectPermissions' => [],
            ])
            ->assertForbidden();
    }

    public function test_rota_roles_permissoes_exige_roles_view(): void
    {
        $user = User::factory()->create(['role' => 'MANAGER']);

        $this->actingAs($user)
            ->get(route('roles.permissions'))
            ->assertForbidden();
    }

    public function test_caixa_nao_pode_aceder_ao_painel_administrativo(): void
    {
        $user = User::factory()->create(['role' => 'CASHIER']);
        $user->assignRole('CASHIER');
        $user->givePermissionTo('sales.view');

        $this->actingAs($user)
            ->get(route('sales.index'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
