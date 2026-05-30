<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReversalRequest;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Services\SaleReversalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\ApiTestHelpers;
use Tests\TestCase;

class SaleReversalServiceTest extends TestCase
{
    use ApiTestHelpers;
    use RefreshDatabase;

    public function test_aprovar_reversao_estorna_stock_e_marca_venda_revertida(): void
    {
        $ambiente = $this->criarAmbienteApi();

        $sale = Sale::query()->create([
            'id' => (string) Str::uuid(),
            'referencia' => 'VD-TEST-001',
            'register_id' => $ambiente['register']->id,
            'source_location_id' => $ambiente['location']->id,
            'user_id' => $ambiente['user']->id,
            'cliente' => 'Cliente Teste',
            'metodo_pagamento' => 'Dinheiro',
            'estado' => 'Concluida',
            'subtotal' => 100,
            'total' => 100,
            'data' => now(),
        ]);

        SaleItem::query()->create([
            'id' => (string) Str::uuid(),
            'sale_id' => $sale->id,
            'produto_id' => $ambiente['product']->id,
            'nome' => $ambiente['product']->nome,
            'quantidade' => 2,
            'preco_venda' => 50,
            'subtotal' => 100,
        ]);

        $balance = StockBalance::query()
            ->where('location_id', $ambiente['location']->id)
            ->where('product_id', $ambiente['product']->id)
            ->first();
        $balance->update(['quantity' => 98]);
        $ambiente['product']->update(['stock' => 98]);

        $pedido = SaleReversalRequest::query()->create([
            'id' => (string) Str::uuid(),
            'sale_id' => $sale->id,
            'requested_by' => $ambiente['user']->id,
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);

        app(SaleReversalService::class)->approve($pedido, 'Teste', $ambiente['user']->id);

        $sale->refresh();
        $balance->refresh();
        $ambiente['product']->refresh();

        $this->assertEquals('Revertida', $sale->estado);
        $this->assertEquals(100.0, (float) $balance->quantity);
        $this->assertEquals(100.0, (float) $ambiente['product']->stock);
        $this->assertTrue(
            StockMovement::query()
                ->where('reference_type', 'SALE_REVERSAL')
                ->where('reference_id', $sale->id)
                ->where('type', 'RETURN')
                ->exists()
        );
    }
}
