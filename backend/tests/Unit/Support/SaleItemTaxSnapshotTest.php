<?php

namespace Tests\Unit\Support;

use App\Models\Product;
use App\Support\SaleItemTaxSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SaleItemTaxSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_deriva_iva_quando_preco_sem_iva_igual_ao_preco_com_iva(): void
    {
        $product = Product::query()->create([
            'id' => (string) Str::uuid(),
            'nome' => 'Alho Grande',
            'preco_venda' => 250,
            'iva_tipo' => 'PERCENTUAL',
            'iva_percentual' => 16,
            'iva_valor' => 0,
            'stock' => 10,
            'is_active' => true,
        ]);

        $tax = SaleItemTaxSnapshot::fromPayload([
            'precoVenda' => 250,
            'precoSemIva' => 250,
            'ivaPercentual' => 0,
            'valorIvaUnitario' => 0,
        ], $product);

        $this->assertSame(16.0, $tax['ivaPercentual']);
        $this->assertSame(215.52, $tax['precoSemIva']);
        $this->assertSame(34.48, $tax['valorIvaUnitario']);
    }
}
