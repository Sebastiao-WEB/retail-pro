<?php

namespace Tests\Feature\Api;

use App\Models\StockBalance;
use App\Models\StockLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\ApiTestHelpers;
use Tests\TestCase;

class InactiveLocationStockTest extends TestCase
{
    use ApiTestHelpers;
    use RefreshDatabase;

    public function test_api_produtos_exclui_stock_de_localizacao_inactiva(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);
        $produto = $ambiente['product'];

        $localInactivo = StockLocation::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'LOC-OFF-API',
            'name' => 'Armazém Inactivo',
            'type' => 'WAREHOUSE',
            'is_saleable' => false,
            'is_active' => false,
        ]);

        StockBalance::query()->create([
            'id' => (string) Str::uuid(),
            'location_id' => $localInactivo->id,
            'product_id' => $produto->id,
            'quantity' => 50,
        ]);

        $produto->update(['stock' => 150]);

        $resposta = $this->getJson('/api/v1/products', $this->authHeaders($token));

        $resposta
            ->assertOk()
            ->assertJsonPath('data.0.stock', 100);
    }

    public function test_disponibilidade_retorna_zero_em_localizacao_inactiva(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);
        $produto = $ambiente['product'];

        $ambiente['location']->update(['is_active' => false]);

        $resposta = $this->getJson(
            '/api/v1/stock/availability?location_id='.$ambiente['location']->id.'&product_ids='.$produto->id,
            $this->authHeaders($token)
        );

        $resposta
            ->assertOk()
            ->assertJsonPath('data.'.$produto->id.'.quantity', 0);
    }

    public function test_venda_rejeita_localizacao_inactiva(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);
        $produto = $ambiente['product'];

        $ambiente['location']->update(['is_active' => false]);

        $resposta = $this->postJson('/api/v1/sales', [
            'cliente' => 'Cliente Geral',
            'metodoPagamento' => 'Dinheiro',
            'subtotal' => 100,
            'total' => 100,
            'valorPago' => 100,
            'source_location_id' => $ambiente['location']->id,
            'itens' => [[
                'produtoId' => $produto->id,
                'nome' => $produto->nome,
                'quantidade' => 1,
                'precoVenda' => 100,
                'subtotal' => 100,
            ]],
        ], $this->authHeaders($token));

        $resposta->assertUnprocessable();
    }

    public function test_recarga_rejeita_localizacao_inactiva(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);
        $produto = $ambiente['product'];

        $ambiente['location']->update(['is_active' => false]);

        $resposta = $this->postJson('/api/v1/stock/reload', [
            'product_id' => $produto->id,
            'quantity' => 5,
            'unit_cost' => 10,
            'to_location_id' => $ambiente['location']->id,
        ], $this->authHeaders($token));

        $resposta->assertUnprocessable();
    }
}
