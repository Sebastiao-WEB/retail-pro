<?php

namespace Tests\Feature\Admin;

use App\Models\Register;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ApiTestHelpers;
use Tests\TestCase;

class UsersPageRoutesTest extends TestCase
{
    use ApiTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('users.manage', 'web');
        Permission::findOrCreate('users.view', 'web');
        $role = Role::findOrCreate('ADMIN', 'web');
        $role->givePermissionTo(['users.manage', 'users.view']);
    }

    public function test_pagina_utilizadores_inclui_data_routes_valido(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $admin = $ambiente['user'];
        $admin->assignRole('ADMIN');
        $admin->givePermissionTo(['users.manage', 'users.view']);

        $html = $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->getContent();

        preg_match("/data-routes='([^']+)'/", $html, $matches);
        $this->assertNotEmpty($matches[1] ?? null);

        $routes = json_decode($matches[1], true);

        $this->assertIsArray($routes, ($matches[1] ?? '').' | json: '.json_last_error_msg());
        $this->assertArrayHasKey('store', $routes);
        $this->assertArrayHasKey('destroy', $routes);
        $this->assertStringContainsString('__ID__', $routes['destroy']);
    }

    public function test_pagina_editar_utilizador_mostra_formulario(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $admin = $ambiente['user'];
        $admin->assignRole('ADMIN');
        $admin->givePermissionTo(['users.manage', 'users.view']);

        $target = $ambiente['user'];
        $target->update(['username' => 'operador.teste']);

        $this->actingAs($admin)
            ->get(route('users.edit', $target))
            ->assertOk()
            ->assertSee('operador.teste', false)
            ->assertSee(route('users.update', $target), false);
    }

    public function test_editar_utilizador_guarda_local_de_stock_escolhido(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $admin = $ambiente['user'];
        $admin->assignRole('ADMIN');
        $admin->givePermissionTo(['users.manage', 'users.view']);

        $register2 = Register::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'CX-02',
            'name' => 'Caixa 02',
            'is_active' => true,
        ]);

        $location2 = StockLocation::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'LOC-CX02',
            'register_id' => $register2->id,
            'name' => 'Loja Caixa 02',
            'type' => 'STORE_FLOOR',
            'is_saleable' => true,
            'is_active' => true,
        ]);

        $operador = User::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Operador Secundário',
            'username' => 'operador.secundario',
            'email' => 'operador.secundario@retailpro.local',
            'password' => bcrypt('123456'),
            'role' => 'CASHIER',
            'register_id' => $ambiente['register']->id,
            'source_location_id' => $ambiente['location']->id,
            'is_active' => true,
        ]);
        $operador->syncAssignedRegisters([$ambiente['register']->id, $register2->id]);

        $this->actingAs($admin)
            ->put(route('users.update', $operador), [
                'name' => $operador->name,
                'username' => $operador->username,
                'email' => $operador->email,
                'role' => 'CASHIER',
                'register_ids' => [$ambiente['register']->id, $register2->id],
                'source_location_id' => $location2->id,
                'is_active' => '1',
            ])
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $operador->id,
            'source_location_id' => $location2->id,
        ]);
    }
}
