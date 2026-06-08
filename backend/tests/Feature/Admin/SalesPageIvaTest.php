<?php

namespace Tests\Feature\Admin;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ApiTestHelpers;
use Tests\TestCase;

class SalesPageIvaTest extends TestCase
{
    use ApiTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('ADMIN', 'web');
    }

    public function test_detalhe_venda_mostra_iva_enriquecido_no_admin(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $produto = $ambiente['product'];
        $produto->update([
            'preco_venda' => 116,
            'iva_tipo' => 'PERCENTUAL',
            'iva_percentual' => 16,
            'iva_valor' => 0,
        ]);

        $admin = User::factory()->create(['role' => 'ADMIN']);
        $admin->assignRole('ADMIN');
        $admin->givePermissionTo('sales.view');

        $sale = Sale::query()->create([
            'id' => (string) Str::uuid(),
            'referencia' => 'VD-TEST-IVA',
            'register_id' => $ambiente['register']->id,
            'user_id' => $admin->id,
            'cliente' => 'Cliente Geral',
            'metodo_pagamento' => 'Dinheiro',
            'estado' => 'Concluida',
            'subtotal' => 116,
            'desconto_aplicado' => 0,
            'total' => 116,
            'valor_pago' => 116,
            'troco' => 0,
            'data' => now(),
        ]);

        SaleItem::query()->create([
            'id' => (string) Str::uuid(),
            'sale_id' => $sale->id,
            'produto_id' => $produto->id,
            'nome' => $produto->nome,
            'quantidade' => 1,
            'preco_venda' => 116,
            'preco_sem_iva' => 116,
            'iva_percentual' => 0,
            'valor_iva_unitario' => 0,
            'subtotal' => 116,
        ]);

        $this->actingAs($admin)
            ->getJson(route('sales.show', $sale))
            ->assertOk()
            ->assertJsonPath('data.itens.0.iva_percentual', '16,00')
            ->assertJsonPath('data.itens.0.iva_total', '16,00');
    }
}
