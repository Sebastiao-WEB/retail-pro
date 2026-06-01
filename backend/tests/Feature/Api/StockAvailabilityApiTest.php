<?php

namespace Tests\Feature\Api;

use App\Models\StockBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ApiTestHelpers;
use Tests\TestCase;

class StockAvailabilityApiTest extends TestCase
{
    use ApiTestHelpers;
    use RefreshDatabase;

    public function test_consulta_stock_disponivel_por_localizacao(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);
        $produto = $ambiente['product'];

        StockBalance::query()
            ->where('location_id', $ambiente['location']->id)
            ->where('product_id', $produto->id)
            ->update(['quantity' => 17]);

        $resposta = $this->getJson(
            '/api/v1/stock/availability?location_id='.$ambiente['location']->id.'&product_ids='.$produto->id,
            $this->authHeaders($token)
        );

        $resposta
            ->assertOk()
            ->assertJsonPath('data.'.$produto->id.'.quantity', 17);

        $this->assertNotEmpty($resposta->json('data.'.$produto->id.'.version'));
    }

    public function test_disponibilidade_usa_stock_global_sem_saldo_local(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);
        $produto = $ambiente['product'];
        $produto->update(['stock' => 33]);

        StockBalance::query()->where('product_id', $produto->id)->delete();

        $resposta = $this->getJson(
            '/api/v1/stock/availability?location_id='.$ambiente['location']->id.'&product_ids='.$produto->id,
            $this->authHeaders($token)
        );

        $resposta
            ->assertOk()
            ->assertJsonPath('data.'.$produto->id.'.quantity', 33);
    }
}
