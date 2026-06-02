<?php

namespace Tests\Feature\Api;

use App\Models\CashSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\ApiTestHelpers;
use Tests\TestCase;

class CashSessionApiTest extends TestCase
{
    use ApiTestHelpers;
    use RefreshDatabase;

    public function test_abre_fecha_e_consulta_sessao_ativa(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);

        $abertura = $this->postJson('/api/v1/cash-sessions/open', [
            'register_id' => $ambiente['register']->id,
            'opening_balance' => 1000,
        ], $this->authHeaders($token));

        $abertura
            ->assertCreated()
            ->assertJsonPath('data.status', 'OPEN');

        $sessaoId = $abertura->json('data.id');

        $ativa = $this->getJson(
            '/api/v1/cash-sessions/active?register_id='.$ambiente['register']->id,
            $this->authHeaders($token)
        );

        $ativa->assertOk()->assertJsonPath('data.id', $sessaoId);

        $fecho = $this->postJson(
            "/api/v1/cash-sessions/{$sessaoId}/close",
            ['closing_balance' => 1200, 'note' => 'Fecho teste'],
            $this->authHeaders($token)
        );

        $fecho
            ->assertOk()
            ->assertJsonPath('data.status', 'CLOSED')
            ->assertJsonPath('data.differenceAmount', 200);
    }

    public function test_impede_abrir_duas_sessoes_no_mesmo_caixa(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);

        CashSession::query()->create([
            'id' => (string) Str::uuid(),
            'register_id' => $ambiente['register']->id,
            'user_id' => $ambiente['user']->id,
            'status' => 'OPEN',
            'opening_balance' => 500,
            'opened_at' => now(),
        ]);

        $resposta = $this->postJson('/api/v1/cash-sessions/open', [
            'register_id' => $ambiente['register']->id,
            'opening_balance' => 300,
        ], $this->authHeaders($token));

        $resposta
            ->assertStatus(409)
            ->assertJsonStructure(['message', 'data' => ['id', 'status']]);
    }

    public function test_lista_fechos_paginados_com_snapshot(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);

        for ($i = 1; $i <= 12; $i++) {
            CashSession::query()->create([
                'id' => (string) Str::uuid(),
                'register_id' => $ambiente['register']->id,
                'user_id' => $ambiente['user']->id,
                'status' => 'CLOSED',
                'opening_balance' => 1000,
                'closing_balance' => 1000 + $i,
                'difference_amount' => $i,
                'opened_at' => now()->subHours($i + 1),
                'closed_at' => now()->subHours($i),
                'report_snapshot' => [
                    'utilizador' => 'Operador Teste',
                    'caixa' => 'Caixa Teste',
                    'totalVendido' => 100 * $i,
                    'totalTransacoes' => $i,
                    'dinheiroReal' => 1000 + $i,
                    'diferenca' => $i,
                ],
            ]);
        }

        $resposta = $this->getJson(
            '/api/v1/cash-sessions?register_id='.$ambiente['register']->id.'&status=CLOSED&page=1&per_page=10',
            $this->authHeaders($token)
        );

        $resposta
            ->assertOk()
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.last_page', 2);

        $this->assertCount(10, $resposta->json('data'));
        $this->assertEquals(100, (float) $resposta->json('data.0.reportSnapshot.totalVendido'));
    }

    public function test_lista_apenas_fechos_do_caixa_atribuido_ao_utilizador(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);

        $outroRegister = \App\Models\Register::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'code' => 'CX-OUTRO',
            'name' => 'Caixa Outro',
            'is_active' => true,
        ]);

        CashSession::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'register_id' => $ambiente['register']->id,
            'user_id' => $ambiente['user']->id,
            'status' => 'CLOSED',
            'opening_balance' => 1000,
            'closing_balance' => 1100,
            'difference_amount' => 100,
            'opened_at' => now()->subHours(2),
            'closed_at' => now()->subHour(),
            'report_snapshot' => ['caixa' => 'Caixa Teste'],
        ]);

        CashSession::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'register_id' => $outroRegister->id,
            'user_id' => $ambiente['user']->id,
            'status' => 'CLOSED',
            'opening_balance' => 500,
            'closing_balance' => 600,
            'difference_amount' => 100,
            'opened_at' => now()->subHours(4),
            'closed_at' => now()->subHours(3),
            'report_snapshot' => ['caixa' => 'Caixa Outro'],
        ]);

        $resposta = $this->getJson(
            '/api/v1/cash-sessions?status=CLOSED',
            $this->authHeaders($token)
        );

        $resposta
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.register_id', $ambiente['register']->id)
            ->assertJsonPath('data.0.reportSnapshot.caixa', 'Caixa Teste');
    }

    public function test_lista_apenas_fechos_do_utilizador_autenticado(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);

        $outroUser = \App\Models\User::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Outro Operador',
            'username' => 'outro_operador',
            'email' => 'outro@retailpro.local',
            'password' => bcrypt('123456'),
            'role' => 'CASHIER',
            'caixa_atribuido' => 'Caixa Teste',
            'register_id' => $ambiente['register']->id,
            'source_location_id' => $ambiente['location']->id,
            'is_active' => true,
        ]);

        CashSession::query()->create([
            'id' => (string) Str::uuid(),
            'register_id' => $ambiente['register']->id,
            'user_id' => $outroUser->id,
            'status' => 'CLOSED',
            'opening_balance' => 500,
            'closing_balance' => 600,
            'difference_amount' => 100,
            'opened_at' => now()->subHours(4),
            'closed_at' => now()->subHours(3),
            'report_snapshot' => ['caixa' => 'Caixa Teste', 'utilizador' => 'Outro Operador'],
        ]);

        CashSession::query()->create([
            'id' => (string) Str::uuid(),
            'register_id' => $ambiente['register']->id,
            'user_id' => $ambiente['user']->id,
            'status' => 'CLOSED',
            'opening_balance' => 1000,
            'closing_balance' => 1100,
            'difference_amount' => 100,
            'opened_at' => now()->subHours(2),
            'closed_at' => now()->subHour(),
            'report_snapshot' => ['caixa' => 'Caixa Teste', 'utilizador' => 'Operador Teste'],
        ]);

        $resposta = $this->getJson(
            '/api/v1/cash-sessions?status=CLOSED',
            $this->authHeaders($token)
        );

        $resposta
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.reportSnapshot.utilizador', 'Operador Teste');
    }

    public function test_rejeita_consulta_de_caixa_diferente_do_atribuido(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);

        $outroRegister = \App\Models\Register::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'code' => 'CX-OUTRO',
            'name' => 'Caixa Outro',
            'is_active' => true,
        ]);

        $this->getJson(
            '/api/v1/cash-sessions?register_id='.$outroRegister->id.'&status=CLOSED',
            $this->authHeaders($token)
        )->assertForbidden();
    }
}
