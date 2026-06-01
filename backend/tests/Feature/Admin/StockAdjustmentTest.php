<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\StockReloadPage;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Services\StockAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ApiTestHelpers;
use Tests\TestCase;

class StockAdjustmentTest extends TestCase
{
    use ApiTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('stock.reload', 'web');
        Permission::findOrCreate('products.view', 'web');
        Role::findOrCreate('ADMIN', 'web')->givePermissionTo(['stock.reload', 'products.view']);
    }

    public function test_corrige_recarga_a_mais_com_ajuste_negativo(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $admin = $ambiente['user'];
        $admin->assignRole('ADMIN');
        $admin->givePermissionTo(['stock.reload', 'products.view']);

        $produto = $ambiente['product'];
        $locationId = $ambiente['location']->id;

        StockBalance::query()
            ->where('location_id', $locationId)
            ->where('product_id', $produto->id)
            ->update(['quantity' => 110]);

        app(StockAdjustmentService::class)->aplicar(
            $produto->id,
            $locationId,
            -3,
            'Correção: entrou 10 em vez de 7',
            $admin->id,
        );

        $this->assertSame(107.0, (float) StockBalance::query()
            ->where('location_id', $locationId)
            ->where('product_id', $produto->id)
            ->value('quantity'));

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $produto->id,
            'type' => 'ADJUSTMENT',
            'reference_type' => 'STOCK_ADJUSTMENT',
            'from_location_id' => $locationId,
        ]);
    }

    public function test_rejeita_ajuste_que_deixa_stock_negativo(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $produto = $ambiente['product'];
        $locationId = $ambiente['location']->id;

        $this->expectException(ValidationException::class);

        app(StockAdjustmentService::class)->aplicar(
            $produto->id,
            $locationId,
            -500,
            null,
            $ambiente['user']->id,
        );
    }

    public function test_pagina_recarga_permite_corrigir_via_livewire(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $admin = $ambiente['user'];
        $admin->assignRole('ADMIN');
        $admin->givePermissionTo(['stock.reload', 'products.view']);

        $produto = $ambiente['product'];

        Livewire::actingAs($admin)
            ->test(StockReloadPage::class)
            ->call('openAdjustModal', $produto->id)
            ->set('adjustmentDelta', '-3')
            ->set('note', 'Correção recarga')
            ->call('applyAdjustment')
            ->assertHasNoErrors();

        $movement = StockMovement::query()
            ->where('product_id', $produto->id)
            ->where('type', 'ADJUSTMENT')
            ->latest()
            ->first();

        $this->assertNotNull($movement);
        $this->assertSame('Correção recarga', $movement->note);
    }
}
