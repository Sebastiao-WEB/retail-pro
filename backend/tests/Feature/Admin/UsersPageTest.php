<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\UsersPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UsersPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('users.manage', 'web');
        Permission::findOrCreate('users.view', 'web');
        $role = Role::findOrCreate('ADMIN', 'web');
        $role->givePermissionTo(['users.manage', 'users.view']);
    }

    public function test_admin_nao_pode_desactivar_a_propria_conta(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN', 'is_active' => true]);
        $admin->assignRole('ADMIN');
        $admin->givePermissionTo(['users.manage', 'users.view']);

        Livewire::actingAs($admin)
            ->test(UsersPage::class)
            ->call('confirmDisable', $admin->id)
            ->assertSet('confirmDisableOpen', false)
            ->assertSet('disableId', null);

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_nao_pode_guardar_propria_conta_como_inactiva(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMIN',
            'is_active' => true,
            'username' => 'admin_teste',
            'email' => 'admin_teste@example.com',
        ]);
        $admin->assignRole('ADMIN');
        $admin->givePermissionTo(['users.manage', 'users.view']);

        Livewire::actingAs($admin)
            ->test(UsersPage::class)
            ->call('openEditModal', $admin->id)
            ->set('is_active', false)
            ->call('save')
            ->assertHasErrors(['is_active']);

        $this->assertTrue($admin->fresh()->is_active);
    }
}
