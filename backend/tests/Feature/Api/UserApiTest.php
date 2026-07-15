<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ApiTestHelpers;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use ApiTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PermissionCatalog::sync('web');
    }

    public function test_lista_utilizadores_com_paginacao(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $admin = $this->criarAdminComPermissao($ambiente['register']->id);
        $token = $this->loginApiAdmin($admin);

        for ($i = 0; $i < 11; $i++) {
            User::query()->create([
                'id' => (string) Str::uuid(),
                'name' => 'Utilizador '.$i,
                'username' => 'user_'.$i,
                'email' => "user{$i}@retailpro.local",
                'password' => bcrypt('123456'),
                'role' => 'CASHIER',
                'register_id' => $ambiente['register']->id,
                'source_location_id' => $ambiente['location']->id,
                'is_active' => true,
            ]);
        }

        $resposta = $this->getJson('/api/v1/users?page=1&per_page=10', $this->authHeaders($token));

        $resposta
            ->assertOk()
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 13)
            ->assertJsonPath('meta.last_page', 2);

        $this->assertCount(10, $resposta->json('data'));
        $this->assertArrayHasKey('registerIds', $resposta->json('data.0'));
    }

    public function test_actualiza_utilizador_por_api(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $admin = $this->criarAdminComPermissao($ambiente['register']->id);
        $token = $this->loginApiAdmin($admin);

        $operador = $ambiente['user'];

        $resposta = $this->putJson('/api/v1/users/'.$operador->id, [
            'name' => 'Operador Actualizado',
            'username' => $operador->username,
            'email' => $operador->email,
            'role' => 'CASHIER',
            'isActive' => true,
            'registerIds' => [$ambiente['register']->id],
            'sourceLocationId' => $ambiente['location']->id,
        ], $this->authHeaders($token));

        $resposta->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $operador->id,
            'name' => 'Operador Actualizado',
        ]);
    }

    public function test_operador_nao_acede_gestao_de_utilizadores(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);

        $this->getJson('/api/v1/users', $this->authHeaders($token))->assertForbidden();
    }

    /** @return User */
    private function criarAdminComPermissao(string $registerId): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'ADMIN', 'guard_name' => 'web']);

        $admin = User::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Admin Mobile',
            'username' => 'admin_mobile',
            'email' => 'admin_mobile@retailpro.local',
            'password' => bcrypt('123456'),
            'role' => 'ADMIN',
            'register_id' => $registerId,
            'is_active' => true,
        ]);

        $admin->assignRole($role);
        $admin->givePermissionTo('users.manage');

        return $admin;
    }

    private function loginApiAdmin(User $admin): string
    {
        $resposta = $this->postJson('/api/v1/auth/admin-login', [
            'username' => $admin->username,
            'password' => '123456',
        ]);

        $resposta->assertOk();

        return (string) $resposta->json('access_token');
    }
}
