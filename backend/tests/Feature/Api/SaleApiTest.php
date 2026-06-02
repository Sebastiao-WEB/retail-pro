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

    public function test_cria_venda_quando_saldo_local_zero_mas_stock_global_disponivel(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);
        $produto = $ambiente['product'];
        $produto->update(['stock' => 25]);

        StockBalance::query()
            ->where('location_id', $ambiente['location']->id)
            ->where('product_id', $produto->id)
            ->update(['quantity' => 0]);

        $resposta = $this->postJson('/api/v1/sales', [
            'cliente' => 'Cliente Geral',
            'register_id' => $ambiente['register']->id,
            'source_location_id' => $ambiente['location']->id,
            'metodoPagamento' => 'Dinheiro',
            'subtotal' => 100,
            'total' => 100,
            'itens' => [
                [
                    'produtoId' => $produto->id,
                    'nome' => $produto->nome,
                    'quantidade' => 1,
                    'precoVenda' => 100,
                    'subtotal' => 100,
                ],
            ],
        ], $this->authHeaders($token));

        $resposta->assertCreated();

        $saldo = StockBalance::query()
            ->where('location_id', $ambiente['location']->id)
            ->where('product_id', $produto->id)
            ->first();

        $this->assertSame(24.0, (float) $saldo->quantity);
    }

    public function test_aceita_venda_com_versao_actual_mesmo_quando_saldo_local_sera_sincronizado(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);
        $produto = $ambiente['product'];
        $produto->update(['stock' => 20]);

        $balance = StockBalance::query()
            ->where('location_id', $ambiente['location']->id)
            ->where('product_id', $produto->id)
            ->first();

        $balance->update(['quantity' => 0]);
        $balance->refresh();

        $versaoActual = (string) optional($balance->updated_at)->toJSON();

        $resposta = $this->postJson('/api/v1/sales', [
            'cliente' => 'Cliente Geral',
            'register_id' => $ambiente['register']->id,
            'source_location_id' => $ambiente['location']->id,
            'metodoPagamento' => 'Dinheiro',
            'subtotal' => 50,
            'total' => 50,
            'stockVersions' => [
                $produto->id => $versaoActual,
            ],
            'itens' => [
                [
                    'produtoId' => $produto->id,
                    'nome' => $produto->nome,
                    'quantidade' => 1,
                    'precoVenda' => 50,
                    'subtotal' => 50,
                ],
            ],
        ], $this->authHeaders($token));

        $resposta->assertCreated();

        $balance->refresh();
        $this->assertSame(19.0, (float) $balance->quantity);
    }

    public function test_rejeita_venda_quando_stock_insuficiente(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);
        $produto = $ambiente['product'];
        $produto->update(['stock' => 1]);

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
            'user_id' => $ambiente['user']->id,
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
            'user_id' => $ambiente['user']->id,
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

        $resposta
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->assertCount(1, $resposta->json('data'));
        $this->assertSame('VD-TEST-A', $resposta->json('data.0.referencia'));
        $this->assertSame($sessaoA, $resposta->json('data.0.cashSessionId'));
    }

    public function test_vincula_venda_a_sessao_aberta_quando_cash_session_id_e_invalido(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);
        $produto = $ambiente['product'];

        $sessao = CashSession::query()->create([
            'id' => (string) Str::uuid(),
            'register_id' => $ambiente['register']->id,
            'user_id' => $ambiente['user']->id,
            'status' => 'OPEN',
            'opening_balance' => 100,
            'opened_at' => now(),
        ]);

        $resposta = $this->postJson('/api/v1/sales', [
            'cliente' => 'Cliente Geral',
            'register_id' => $ambiente['register']->id,
            'source_location_id' => $ambiente['location']->id,
            'cash_session_id' => (string) Str::uuid(),
            'metodoPagamento' => 'Dinheiro',
            'subtotal' => 50,
            'total' => 50,
            'itens' => [
                [
                    'produtoId' => $produto->id,
                    'nome' => $produto->nome,
                    'quantidade' => 1,
                    'precoVenda' => 50,
                    'subtotal' => 50,
                ],
            ],
        ], $this->authHeaders($token));

        $resposta->assertCreated()->assertJsonPath('data.cashSessionId', $sessao->id);

        $this->assertDatabaseHas('sales', [
            'cash_session_id' => $sessao->id,
            'register_id' => $ambiente['register']->id,
        ]);
    }

    public function test_vincula_venda_a_sessao_aberta_quando_cash_session_id_nao_enviado(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);
        $produto = $ambiente['product'];

        $sessao = CashSession::query()->create([
            'id' => (string) Str::uuid(),
            'register_id' => $ambiente['register']->id,
            'user_id' => $ambiente['user']->id,
            'status' => 'OPEN',
            'opening_balance' => 100,
            'opened_at' => now(),
        ]);

        $resposta = $this->postJson('/api/v1/sales', [
            'cliente' => 'Cliente Geral',
            'register_id' => $ambiente['register']->id,
            'source_location_id' => $ambiente['location']->id,
            'metodoPagamento' => 'Dinheiro',
            'subtotal' => 30,
            'total' => 30,
            'itens' => [
                [
                    'produtoId' => $produto->id,
                    'nome' => $produto->nome,
                    'quantidade' => 1,
                    'precoVenda' => 30,
                    'subtotal' => 30,
                ],
            ],
        ], $this->authHeaders($token));

        $resposta->assertCreated()->assertJsonPath('data.cashSessionId', $sessao->id);
    }

    public function test_lista_vendas_com_paginacao(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);
        $sessao = (string) Str::uuid();

        for ($i = 0; $i < 12; $i++) {
            Sale::query()->create([
                'id' => (string) Str::uuid(),
                'referencia' => 'VD-PAG-'.$i,
                'register_id' => $ambiente['register']->id,
                'user_id' => $ambiente['user']->id,
                'cash_session_id' => $sessao,
                'cliente' => 'Cliente Geral',
                'metodo_pagamento' => 'Dinheiro',
                'subtotal' => 10,
                'total' => 10,
                'data' => now()->subMinutes($i),
            ]);
        }

        $resposta = $this->getJson(
            '/api/v1/sales?cash_session_id='.$sessao.'&page=1&per_page=10',
            $this->authHeaders($token)
        );

        $resposta
            ->assertOk()
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.last_page', 2);

        $this->assertCount(10, $resposta->json('data'));
    }

    public function test_rejeita_reenvio_com_mesmo_id_e_conteudo_diferente(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);
        $produto = $ambiente['product'];
        $saleId = (string) Str::uuid();

        $base = [
            'id' => $saleId,
            'cliente' => 'Cliente Geral',
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

        $this->postJson('/api/v1/sales', $base, $this->authHeaders($token))->assertCreated();

        $conflito = $base;
        $conflito['itens'][0]['quantidade'] = 17;
        $conflito['subtotal'] = 1700;
        $conflito['total'] = 1700;

        $this->postJson('/api/v1/sales', $conflito, $this->authHeaders($token))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id']);

        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseHas('sale_items', ['sale_id' => $saleId, 'quantidade' => 1]);
    }

    public function test_rejeita_venda_quando_versao_de_stock_esta_desatualizada(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);
        $produto = $ambiente['product'];

        $balance = StockBalance::query()
            ->where('location_id', $ambiente['location']->id)
            ->where('product_id', $produto->id)
            ->first();

        $balance->touch();

        $resposta = $this->postJson('/api/v1/sales', [
            'cliente' => 'Cliente Geral',
            'register_id' => $ambiente['register']->id,
            'source_location_id' => $ambiente['location']->id,
            'metodoPagamento' => 'Dinheiro',
            'subtotal' => 100,
            'total' => 100,
            'stockVersions' => [
                $produto->id => '2000-01-01T00:00:00.000000Z',
            ],
            'itens' => [
                [
                    'produtoId' => $produto->id,
                    'nome' => $produto->nome,
                    'quantidade' => 1,
                    'precoVenda' => 100,
                    'subtotal' => 100,
                ],
            ],
        ], $this->authHeaders($token));

        $resposta->assertStatus(422);
        $this->assertDatabaseCount('sales', 0);
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

    public function test_persiste_e_devolve_iva_quando_pos_nao_envia_campos_de_imposto(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);
        $produto = $ambiente['product'];
        $produto->update([
            'preco_venda' => 116,
            'iva_tipo' => 'PERCENTUAL',
            'iva_percentual' => 16,
            'iva_valor' => 0,
        ]);

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
            'register_id' => $ambiente['register']->id,
            'source_location_id' => $ambiente['location']->id,
            'cash_session_id' => $sessao->id,
            'metodoPagamento' => 'Dinheiro',
            'subtotal' => 116,
            'total' => 116,
            'valorPago' => 116,
            'troco' => 0,
            'itens' => [
                [
                    'produtoId' => $produto->id,
                    'nome' => $produto->nome,
                    'quantidade' => 1,
                    'precoVenda' => 116,
                    'subtotal' => 116,
                ],
            ],
        ], $this->authHeaders($token));

        $resposta
            ->assertCreated()
            ->assertJsonPath('data.itens.0.ivaPercentual', 16)
            ->assertJsonPath('data.itens.0.precoSemIva', 100)
            ->assertJsonPath('data.itens.0.valorIvaUnitario', 16);

        $this->assertDatabaseHas('sale_items', [
            'produto_id' => $produto->id,
            'iva_percentual' => 16,
            'preco_sem_iva' => 100,
            'valor_iva_unitario' => 16,
        ]);

        $listagem = $this->getJson('/api/v1/sales?register_id='.$ambiente['register']->id, $this->authHeaders($token));
        $listagem
            ->assertOk()
            ->assertJsonPath('data.0.itens.0.ivaPercentual', 16)
            ->assertJsonPath('data.0.itens.0.valorIvaUnitario', 16);
    }

    public function test_lista_apenas_vendas_do_utilizador_autenticado(): void
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

        Sale::query()->create([
            'id' => (string) Str::uuid(),
            'referencia' => 'VD-OUTRO-001',
            'cliente' => 'Cliente Geral',
            'caixa' => 'Caixa Teste',
            'operador' => 'Outro Operador',
            'register_id' => $ambiente['register']->id,
            'user_id' => $outroUser->id,
            'source_location_id' => $ambiente['location']->id,
            'metodo_pagamento' => 'Dinheiro',
            'estado' => 'Concluida',
            'subtotal' => 100,
            'total' => 100,
            'valor_pago' => 100,
            'troco' => 0,
            'data' => now()->subHour(),
        ]);

        Sale::query()->create([
            'id' => (string) Str::uuid(),
            'referencia' => 'VD-MEU-001',
            'cliente' => 'Cliente Geral',
            'caixa' => 'Caixa Teste',
            'operador' => 'Operador Teste',
            'register_id' => $ambiente['register']->id,
            'user_id' => $ambiente['user']->id,
            'source_location_id' => $ambiente['location']->id,
            'metodo_pagamento' => 'Dinheiro',
            'estado' => 'Concluida',
            'subtotal' => 200,
            'total' => 200,
            'valor_pago' => 200,
            'troco' => 0,
            'data' => now(),
        ]);

        $resposta = $this->getJson(
            '/api/v1/sales?register_id='.$ambiente['register']->id,
            $this->authHeaders($token)
        );

        $resposta
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.referencia', 'VD-MEU-001');
    }
}
