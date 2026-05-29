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
            ->assertJsonPath('data.difference_amount', 200);
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
}
