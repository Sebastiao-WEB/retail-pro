<?php

namespace Tests\Feature\Api;

use App\Models\CashSession;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockBalance;
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
        $produto = $ambiente['product'];

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

        $produto->refresh();
        $this->assertSame(98.0, (float) $produto->stock);

        $this->assertDatabaseHas('stock_balances', [
            'location_id' => $ambiente['location']->id,
            'product_id' => $produto->id,
            'quantity' => 98,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $produto->id,
            'from_location_id' => $ambiente['location']->id,
            'type' => 'OUT',
            'reference_type' => 'SALE',
            'quantity' => 2,
        ]);
    }

    public function test_rejeita_venda_quando_stock_insuficiente(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);
        $produto = $ambiente['product'];

        StockBalance::query()
            ->where('location_id', $ambiente['location']->id)
            ->where('product_id', $produto->id)
            ->update(['quantity' => 1]);

        $resposta = $this->postJson('/api/v1/sales', [
            'cliente' => 'Cliente Geral',
            'register_id' => $ambiente['register']->id,
            'source_location_id' => $ambiente['location']->id,
            'metodoPagamento' => 'Dinheiro',
            'subtotal' => 200,
            'total' => 200,
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

        $resposta->assertStatus(422);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('stock_movements', 0);

        $saldo = StockBalance::query()
            ->where('location_id', $ambiente['location']->id)
            ->where('product_id', $produto->id)
            ->first();

        $this->assertSame(1.0, (float) $saldo->quantity);
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

    public function test_gera_referencias_unicas_quando_nao_informadas(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);
        $produto = $ambiente['product'];

        $payload = [
            'cliente' => 'Cliente Geral',
            'caixa' => 'Caixa Teste',
            'operador' => 'Operador Teste',
            'register_id' => $ambiente['register']->id,
            'source_location_id' => $ambiente['location']->id,
            'metodoPagamento' => 'Dinheiro',
            'subtotal' => 100,
            'total' => 100,
            'valorPago' => 100,
            'troco' => 0,
            'itens' => [
                [
                    'produtoId' => $produto->id,
                    'nome' => $produto->nome,
                    'quantidade' => 1,
                    'precoVenda' => 100,
                    'subtotal' => 100,
                ],
            ],
        ];

        $primeira = $this->postJson('/api/v1/sales', $payload, $this->authHeaders($token));
        $segunda = $this->postJson('/api/v1/sales', $payload, $this->authHeaders($token));

        $primeira->assertCreated();
        $segunda->assertCreated();

        $referenciaA = $primeira->json('data.referencia');
        $referenciaB = $segunda->json('data.referencia');

        $this->assertNotSame($referenciaA, $referenciaB);
        $this->assertMatchesRegularExpression('/^VD-\d{8}-\d{6}-[A-Z0-9]{4}$/', $referenciaA);
    }

    public function test_reutiliza_venda_quando_id_ja_existe(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);
        $produto = $ambiente['product'];
        $saleId = (string) Str::uuid();

        $payload = [
            'id' => $saleId,
            'cliente' => 'Cliente Geral',
            'caixa' => 'Caixa Teste',
            'operador' => 'Operador Teste',
            'register_id' => $ambiente['register']->id,
            'source_location_id' => $ambiente['location']->id,
            'metodoPagamento' => 'Dinheiro',
            'subtotal' => 100,
            'total' => 100,
            'valorPago' => 100,
            'troco' => 0,
            'itens' => [
                [
                    'produtoId' => $produto->id,
                    'nome' => $produto->nome,
                    'quantidade' => 1,
                    'precoVenda' => 100,
                    'subtotal' => 100,
                ],
            ],
        ];

        $primeira = $this->postJson('/api/v1/sales', $payload, $this->authHeaders($token));
        $segunda = $this->postJson('/api/v1/sales', $payload, $this->authHeaders($token));

        $primeira->assertCreated();
        $segunda->assertCreated();

        $this->assertDatabaseCount('sales', 1);
        $this->assertSame($saleId, $segunda->json('data.id'));

        $produto->refresh();
        $this->assertSame(99.0, (float) $produto->stock);
    }
}
