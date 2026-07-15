<?php

namespace Tests\Feature\Api;

use App\Models\DiningTable;
use App\Models\TableOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\ApiTestHelpers;
use Tests\TestCase;

class DiningTableApiTest extends TestCase
{
    use ApiTestHelpers;
    use RefreshDatabase;

    public function test_cria_mesa_com_codigo_unico_e_abre_comanda(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);

        $mesaResposta = $this->postJson('/api/v1/dining-tables', [
            'code' => 'MESA-01',
            'name' => 'Terraço 1',
            'registerId' => $ambiente['register']->id,
        ], $this->authHeaders($token));

        $mesaResposta
            ->assertCreated()
            ->assertJsonPath('data.code', 'MESA-01');

        $mesaId = $mesaResposta->json('data.id');

        $pedidoResposta = $this->postJson('/api/v1/table-orders', [
            'diningTableId' => $mesaId,
            'description' => 'Grupo aniversário',
            'registerId' => $ambiente['register']->id,
        ], $this->authHeaders($token));

        $pedidoResposta
            ->assertCreated()
            ->assertJsonPath('data.status', 'OPEN')
            ->assertJsonPath('data.description', 'Grupo aniversário');

        $this->assertDatabaseHas('table_orders', [
            'dining_table_id' => $mesaId,
            'status' => 'OPEN',
        ]);
    }

    public function test_transfere_itens_entre_mesas(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);

        $mesaOrigem = DiningTable::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'MESA-A',
            'register_id' => $ambiente['register']->id,
            'is_active' => true,
        ]);
        $mesaDestino = DiningTable::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'MESA-B',
            'register_id' => $ambiente['register']->id,
            'is_active' => true,
        ]);

        $pedidoOrigem = TableOrder::query()->create([
            'id' => (string) Str::uuid(),
            'dining_table_id' => $mesaOrigem->id,
            'register_id' => $ambiente['register']->id,
            'status' => 'OPEN',
            'opened_at' => now(),
        ]);

        $itemResposta = $this->postJson("/api/v1/table-orders/{$pedidoOrigem->id}/items", [
            'itens' => [[
                'nome' => 'Cerveja',
                'quantidade' => 2,
                'precoVenda' => 80,
                'subtotal' => 160,
            ]],
        ], $this->authHeaders($token));

        $itemId = $itemResposta->json('data.itens.0.id');

        $transferencia = $this->postJson("/api/v1/table-orders/{$pedidoOrigem->id}/transfer", [
            'toTableId' => $mesaDestino->id,
            'itemIds' => [$itemId],
        ], $this->authHeaders($token));

        $transferencia->assertOk();
        $this->assertDatabaseHas('table_orders', [
            'dining_table_id' => $mesaDestino->id,
            'status' => 'OPEN',
        ]);
        $this->assertDatabaseHas('table_order_items', [
            'nome' => 'Cerveja',
            'quantidade' => 2,
        ]);
    }

    public function test_liquida_itens_parcialmente_sem_fechar_comanda(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);

        $mesa = DiningTable::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'MESA-C',
            'register_id' => $ambiente['register']->id,
            'is_active' => true,
        ]);

        $pedido = TableOrder::query()->create([
            'id' => (string) Str::uuid(),
            'dining_table_id' => $mesa->id,
            'register_id' => $ambiente['register']->id,
            'status' => 'OPEN',
            'opened_at' => now(),
        ]);

        $itensResposta = $this->postJson("/api/v1/table-orders/{$pedido->id}/items", [
            'itens' => [
                [
                    'nome' => 'Cerveja',
                    'quantidade' => 3,
                    'precoVenda' => 80,
                    'subtotal' => 240,
                ],
                [
                    'nome' => 'Refrigerante',
                    'quantidade' => 1,
                    'precoVenda' => 50,
                    'subtotal' => 50,
                ],
            ],
        ], $this->authHeaders($token));

        $cervejaId = $itensResposta->json('data.itens.0.id');
        $refrigeranteId = $itensResposta->json('data.itens.1.id');

        $liquidacao = $this->postJson("/api/v1/table-orders/{$pedido->id}/settle-items", [
            'itens' => [[
                'itemId' => $cervejaId,
                'quantidade' => 2,
            ]],
        ], $this->authHeaders($token));

        $liquidacao
            ->assertOk()
            ->assertJsonPath('data.status', 'OPEN')
            ->assertJsonCount(2, 'data.itens');

        $this->assertDatabaseHas('table_order_items', [
            'id' => $cervejaId,
            'quantidade' => 1,
        ]);
        $this->assertDatabaseHas('table_order_items', [
            'id' => $refrigeranteId,
            'quantidade' => 1,
        ]);

        $liquidacaoFinal = $this->postJson("/api/v1/table-orders/{$pedido->id}/settle-items", [
            'itens' => [
                ['itemId' => $cervejaId, 'quantidade' => 1],
                ['itemId' => $refrigeranteId, 'quantidade' => 1],
            ],
        ], $this->authHeaders($token));

        $liquidacaoFinal
            ->assertOk()
            ->assertJsonPath('data.status', 'CLOSED')
            ->assertJsonCount(0, 'data.itens');
    }
}
