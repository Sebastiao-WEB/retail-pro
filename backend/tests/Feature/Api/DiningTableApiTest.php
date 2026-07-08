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
}
