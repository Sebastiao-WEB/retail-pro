<?php

namespace Tests\Feature\Api;

use App\Models\Sale;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ApiTestHelpers;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use ApiTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PermissionCatalog::sync('web');
    }

    public function test_dashboard_summary_requer_autenticacao(): void
    {
        $this->getJson('/api/v1/dashboard/summary')->assertUnauthorized();
    }

    public function test_dashboard_summary_para_admin(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $admin = $this->criarAdmin($ambiente['register']->id);

        Sale::query()->create([
            'id' => (string) Str::uuid(),
            'referencia' => 'VD-TEST-001',
            'register_id' => $ambiente['register']->id,
            'source_location_id' => $ambiente['location']->id,
            'user_id' => $admin->id,
            'cliente' => 'Cliente Geral',
            'caixa' => 'Caixa Teste',
            'metodo_pagamento' => 'Dinheiro',
            'estado' => 'Concluida',
            'subtotal' => 100,
            'desconto_aplicado' => 0,
            'total' => 100,
            'valor_pago' => 100,
            'troco' => 0,
            'data' => now(),
        ]);

        $token = $this->loginApiAdmin($admin);

        $resposta = $this->getJson('/api/v1/dashboard/summary?period=today', $this->authHeaders($token));

        $resposta
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'metrics' => [
                        'totalVendasPeriodo',
                        'totalProdutos',
                        'totalClientes',
                        'recargasMes',
                        'reversoesPendentes',
                        'caixasAtivos',
                    ],
                    'charts' => ['vendasPorDia', 'metodosPagamento'],
                    'ultimasVendas',
                ],
            ]);

        $this->assertSame(100.0, (float) $resposta->json('data.metrics.totalVendasPeriodo'));
    }

    public function test_operador_nao_acede_dashboard_summary(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user'], 'Caixa Teste');

        $this->getJson('/api/v1/dashboard/summary', $this->authHeaders($token))
            ->assertForbidden();
    }

    private function criarAdmin(string $registerId): User
    {
        Role::findOrCreate('ADMIN', 'web');

        $admin = User::query()->create([
            'name' => 'Admin Dashboard',
            'username' => 'admin_dashboard',
            'email' => 'admin_dashboard@retailpro.local',
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
