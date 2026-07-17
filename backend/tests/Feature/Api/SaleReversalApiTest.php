<?php

namespace Tests\Feature\Api;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReversalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\ApiTestHelpers;
use Tests\TestCase;

class SaleReversalApiTest extends TestCase
{
    use ApiTestHelpers;
    use RefreshDatabase;

    public function test_cria_solicitacao_de_reversao_com_venda_id(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);

        $venda = Sale::query()->create([
            'id' => (string) Str::uuid(),
            'referencia' => 'VD-REV-001',
            'register_id' => $ambiente['register']->id,
            'cliente' => 'Cliente Geral',
            'metodo_pagamento' => 'Dinheiro',
            'subtotal' => 150,
            'total' => 150,
            'data' => now(),
        ]);

        $resposta = $this->postJson('/api/v1/sale-reversal-requests', [
            'venda_id' => $venda->id,
            'reason' => 'Cliente desistiu',
        ], $this->authHeaders($token));

        $resposta
            ->assertCreated()
            ->assertJsonPath('data.status', 'PENDING');

        $this->assertDatabaseHas('sale_reversal_requests', [
            'sale_id' => $venda->id,
            'status' => 'PENDING',
        ]);
    }

    public function test_impede_solicitacao_duplicada_pendente(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);

        $venda = Sale::query()->create([
            'id' => (string) Str::uuid(),
            'referencia' => 'VD-REV-002',
            'register_id' => $ambiente['register']->id,
            'cliente' => 'Cliente Geral',
            'metodo_pagamento' => 'Dinheiro',
            'subtotal' => 80,
            'total' => 80,
            'data' => now(),
        ]);

        SaleReversalRequest::query()->create([
            'id' => (string) Str::uuid(),
            'sale_id' => $venda->id,
            'requested_by' => $ambiente['user']->id,
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);

        $resposta = $this->postJson('/api/v1/sale-reversal-requests', [
            'venda_id' => $venda->id,
            'reason' => 'Duplicada',
        ], $this->authHeaders($token));

        $resposta
            ->assertOk()
            ->assertJsonPath('data.reutilizada', true)
            ->assertJsonPath('data.status', 'PENDING');

        $this->assertSame(
            1,
            SaleReversalRequest::query()->where('sale_id', $venda->id)->where('status', 'PENDING')->count()
        );
    }

    public function test_lista_reversoes_paginadas_com_detalhes_da_venda(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);

        $venda = Sale::query()->create([
            'id' => (string) Str::uuid(),
            'referencia' => 'VD-REV-LIST',
            'register_id' => $ambiente['register']->id,
            'cliente' => 'Cliente Geral',
            'caixa' => 'Caixa Teste',
            'metodo_pagamento' => 'Dinheiro',
            'subtotal' => 200,
            'total' => 200,
            'data' => now(),
        ]);

        SaleItem::query()->create([
            'id' => (string) Str::uuid(),
            'sale_id' => $venda->id,
            'produto_id' => $ambiente['product']->id,
            'nome' => 'Produto Teste',
            'quantidade' => 2,
            'preco_venda' => 100,
            'preco_sem_iva' => 100,
            'iva_percentual' => 0,
            'valor_iva_unitario' => 0,
            'subtotal' => 200,
        ]);

        SaleReversalRequest::query()->create([
            'id' => (string) Str::uuid(),
            'sale_id' => $venda->id,
            'requested_by' => $ambiente['user']->id,
            'status' => 'PENDING',
            'reason' => 'Cliente devolveu artigo',
            'requested_at' => now(),
        ]);

        $resposta = $this->getJson('/api/v1/sale-reversal-requests?page=1&per_page=10', $this->authHeaders($token));

        $resposta
            ->assertOk()
            ->assertJsonPath('data.meta.per_page', 10)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.reason', 'Cliente devolveu artigo')
            ->assertJsonPath('data.items.0.requestedBy', 'Operador Teste')
            ->assertJsonPath('data.items.0.sale.referencia', 'VD-REV-LIST')
            ->assertJsonPath('data.items.0.sale.itens.0.nome', 'Produto Teste');
    }

    public function test_operador_pos_so_ve_as_suas_solicitacoes_de_reversao(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $ambiente['user']->registers()->sync([$ambiente['register']->id]);

        $outro = \App\Models\User::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Outro Operador',
            'username' => 'outro_operador_rev',
            'email' => 'outro_operador_rev@retailpro.local',
            'password' => bcrypt('123456'),
            'role' => 'CASHIER',
            'register_id' => $ambiente['register']->id,
            'source_location_id' => $ambiente['location']->id,
            'is_active' => true,
        ]);
        $outro->registers()->sync([$ambiente['register']->id]);

        $vendaMinha = Sale::query()->create([
            'id' => (string) Str::uuid(),
            'referencia' => 'VD-REV-MINHA',
            'register_id' => $ambiente['register']->id,
            'user_id' => $ambiente['user']->id,
            'cliente' => 'Cliente Geral',
            'metodo_pagamento' => 'Dinheiro',
            'subtotal' => 100,
            'total' => 100,
            'data' => now(),
        ]);

        $vendaOutro = Sale::query()->create([
            'id' => (string) Str::uuid(),
            'referencia' => 'VD-REV-OUTRO',
            'register_id' => $ambiente['register']->id,
            'user_id' => $outro->id,
            'cliente' => 'Cliente Geral',
            'metodo_pagamento' => 'Dinheiro',
            'subtotal' => 200,
            'total' => 200,
            'data' => now(),
        ]);

        SaleReversalRequest::query()->create([
            'id' => (string) Str::uuid(),
            'sale_id' => $vendaMinha->id,
            'requested_by' => $ambiente['user']->id,
            'status' => 'PENDING',
            'reason' => 'Minha solicitação',
            'requested_at' => now(),
        ]);

        SaleReversalRequest::query()->create([
            'id' => (string) Str::uuid(),
            'sale_id' => $vendaOutro->id,
            'requested_by' => $outro->id,
            'status' => 'PENDING',
            'reason' => 'Solicitação de outro',
            'requested_at' => now(),
        ]);

        $token = $this->loginApi($ambiente['user']);
        $resposta = $this->getJson('/api/v1/sale-reversal-requests?page=1&per_page=10', $this->authHeaders($token));

        $resposta
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.reason', 'Minha solicitação')
            ->assertJsonPath('data.items.0.sale.referencia', 'VD-REV-MINHA');
    }
}
