<?php

namespace Tests\Feature\Api;

use App\Models\CashSession;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\ApiTestHelpers;
use Tests\TestCase;

class SaleApiTest extends TestCase
{
    use ApiTestHelpers;
    use RefreshDatabase;

    public function test_cria_venda_com_cash_session_id(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);
        $produto = Product::query()->first();

        $sessao = CashSession::query()->create([
            'id' => (string) Str::uuid(),
            'register_id' => $ambiente['register']->id,
            'user_id' => $ambiente['user']->id,
            'status' => 'OPEN',
            'opening_balance' => 500,
            'opened_at' => now(),
        ]);

        $resposta = $this->postJson('/api/v1/sales', [
            'cliente' => 'Cliente Geral',
            'caixa' => 'Caixa Teste',
            'operador' => 'Operador Teste',
            'register_id' => $ambiente['register']->id,
            'source_location_id' => $ambiente['location']->id,
            'cash_session_id' => $sessao->id,
            'metodoPagamento' => 'Dinheiro',
            'subtotal' => 200,
            'total' => 200,
            'valorPago' => 200,
            'troco' => 0,
            'itens' => [
                [
                    'produtoId' => $produto->id,
                    'nome' => $produto->nome,
                    'quantidade' => 2,
                    'precoVenda' => 100,
                    'subtotal' => 200,
                ],
            ],
        ], $this->authHeaders($token));

        $resposta
            ->assertCreated()
            ->assertJsonPath('data.status', 'COMPLETED');

        $this->assertDatabaseHas('sales', [
            'cash_session_id' => $sessao->id,
            'register_id' => $ambiente['register']->id,
        ]);
    }

    public function test_lista_vendas_filtradas_por_cash_session_id(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);

        $sessaoA = (string) Str::uuid();
        $sessaoB = (string) Str::uuid();

        Sale::query()->create([
            'id' => (string) Str::uuid(),
            'referencia' => 'VD-TEST-A',
            'register_id' => $ambiente['register']->id,
            'cash_session_id' => $sessaoA,
            'cliente' => 'Cliente Geral',
            'metodo_pagamento' => 'Dinheiro',
            'subtotal' => 100,
            'total' => 100,
            'data' => now(),
        ]);

        Sale::query()->create([
            'id' => (string) Str::uuid(),
            'referencia' => 'VD-TEST-B',
            'register_id' => $ambiente['register']->id,
            'cash_session_id' => $sessaoB,
            'cliente' => 'Cliente Geral',
            'metodo_pagamento' => 'Dinheiro',
            'subtotal' => 300,
            'total' => 300,
            'data' => now(),
        ]);

        $resposta = $this->getJson(
            '/api/v1/sales?cash_session_id='.$sessaoA,
            $this->authHeaders($token)
        );

        $resposta->assertOk();
        $this->assertCount(1, $resposta->json('data'));
        $this->assertSame('VD-TEST-A', $resposta->json('data.0.referencia'));
    }
}
