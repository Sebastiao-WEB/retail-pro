<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\Register;
use App\Models\StockBalance;
use App\Models\StockLocation;
use App\Services\StockByLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockByLocationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_stock_disponivel_por_localizacao(): void
    {
        $register = Register::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'CX-LOC',
            'name' => 'Caixa Loc',
            'is_active' => true,
        ]);

        $location = StockLocation::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'LOC-TEST',
            'register_id' => $register->id,
            'name' => 'Loja Teste',
            'type' => 'STORE_FLOOR',
            'is_saleable' => true,
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'id' => (string) Str::uuid(),
            'nome' => 'Produto Local',
            'codigo_barras' => '1234567890123',
            'preco_compra' => 10,
            'preco_venda' => 15,
            'iva_tipo' => 'ISENTO',
            'iva_valor' => 0,
            'iva_percentual' => 0,
            'stock' => 25,
            'is_active' => true,
        ]);

        StockBalance::query()->create([
            'id' => (string) Str::uuid(),
            'location_id' => $location->id,
            'product_id' => $product->id,
            'quantity' => 25,
        ]);

        $service = app(StockByLocationService::class);
        $resumo = $service->resumoPorLocalizacao($location->id);

        $this->assertCount(1, $resumo);
        $this->assertEquals(25.0, $resumo[0]['total_qtd']);
        $this->assertEquals(25.0, $service->quantidadeDisponivel($location->id, $product->id));
    }
}
