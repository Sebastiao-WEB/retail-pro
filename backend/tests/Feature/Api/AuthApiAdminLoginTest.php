<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ApiTestHelpers;
use Tests\TestCase;

class AuthApiAdminLoginTest extends TestCase
{
    use ApiTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PermissionCatalog::sync('web');
    }

    public function test_admin_login_emite_jwt_sem_caixa(): void
    {
        $admin = $this->criarUtilizadorAdminApi();

        $resposta = $this->postJson('/api/v1/auth/admin-login', [
            'username' => $admin->username,
            'password' => '123456',
        ]);

        $resposta
            ->assertOk()
            ->assertJson([
                'client' => 'admin',
            ])
            ->assertJsonStructure([
                'access_token',
                'user' => ['id', 'name', 'roles', 'permissions', 'registers'],
            ]);
    }

    public function test_operador_nao_acede_admin_login(): void
    {
        $ambiente = $this->criarAmbienteApi();

        $this->postJson('/api/v1/auth/admin-login', [
            'username' => $ambiente['user']->username,
            'password' => '123456',
        ])->assertForbidden();
    }

    public function test_auth_me_retorna_perfil_admin(): void
    {
        $admin = $this->criarUtilizadorAdminApi();
        $token = $this->loginApiAdmin($admin);

        $this->getJson('/api/v1/auth/me', $this->authHeaders($token))
            ->assertOk()
            ->assertJson([
                'client' => 'admin',
                'user' => [
                    'id' => $admin->id,
                    'username' => $admin->username,
                ],
            ])
            ->assertJsonStructure([
                'user' => ['roles', 'permissions'],
            ]);
    }

    public function test_auth_me_admin_com_caixa_atribuido_continua_admin(): void
    {
        $ambiente = $this->criarAmbienteApi();
        Role::findOrCreate('ADMIN', 'web');

        $admin = User::query()->create([
            'name' => 'Admin Com Caixa',
            'username' => 'admin_com_caixa',
            'email' => 'admin_com_caixa@retailpro.local',
            'password' => bcrypt('123456'),
            'role' => 'ADMIN',
            'is_active' => true,
            'register_id' => $ambiente['register']->id,
        ]);
        $admin->syncRoles(['ADMIN']);
        $admin->registers()->sync([$ambiente['register']->id]);

        $token = $this->loginApiAdmin($admin);

        $this->getJson('/api/v1/auth/me', $this->authHeaders($token))
            ->assertOk()
            ->assertJson([
                'client' => 'admin',
                'user' => [
                    'id' => $admin->id,
                    'username' => $admin->username,
                ],
            ]);
    }

    private function criarUtilizadorAdminApi(): User
    {
        Role::findOrCreate('ADMIN', 'web');

        $admin = User::query()->create([
            'name' => 'Admin Mobile',
            'username' => 'admin_mobile',
            'email' => 'admin_mobile@retailpro.local',
            'password' => bcrypt('123456'),
            'role' => 'ADMIN',
            'is_active' => true,
        ]);

        $admin->syncRoles(['ADMIN']);

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
