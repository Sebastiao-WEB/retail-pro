<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\Register;
use App\Models\StockBalance;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\BalanceSheetBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BalanceSheetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('balance_sheets.view', 'web');
        Permission::findOrCreate('balance_sheets.manage', 'web');
        $role = Role::findOrCreate('ADMIN', 'web');
        $role->givePermissionTo(['balance_sheets.view', 'balance_sheets.manage']);
    }

    public function test_cria_balanco_com_linhas_por_produto(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $user->assignRole('ADMIN');

        $register = Register::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'CX-BAL',
            'name' => 'Caixa Balanço',
            'is_active' => true,
        ]);

        $location = StockLocation::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'LOC-BAL',
            'register_id' => $register->id,
            'name' => 'Loja Balanço',
            'type' => 'STORE_FLOOR',
            'is_saleable' => true,
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'id' => (string) Str::uuid(),
            'nome' => 'Produto Balanço',
            'codigo_barras' => '8888888888888',
            'preco_compra' => 10,
            'preco_venda' => 15,
            'iva_tipo' => 'ISENTO',
            'iva_valor' => 0,
            'iva_percentual' => 0,
            'stock' => 5,
            'is_active' => true,
        ]);

        StockMovement::query()->create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'to_location_id' => $location->id,
            'type' => 'IN',
            'quantity' => 5,
            'unit_cost' => 10,
            'reference_type' => 'STOCK_RELOAD',
            'reference_id' => (string) Str::uuid(),
        ]);

        StockBalance::query()->create([
            'id' => (string) Str::uuid(),
            'location_id' => $location->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $builder = app(BalanceSheetBuilder::class);
        $balance = $builder->create([
            'titulo' => 'Balanço Teste',
            'data_referencia' => now()->toDateString(),
            'periodo_inicio' => now()->startOfMonth()->toDateString(),
            'periodo_fim' => now()->toDateString(),
        ], $user->id);

        $this->assertDatabaseHas('balance_sheets', [
            'id' => $balance->id,
            'status' => 'DRAFT',
        ]);

        $this->assertSame(1, $balance->lines()->count());
        $this->assertEquals(50.0, (float) $balance->total_recargas_valor);
        $this->assertEquals(50.0, (float) $balance->total_stock_valor_compra);
        $this->assertEquals(75.0, (float) $balance->total_stock_valor_venda);
    }

    public function test_pagina_balanco_requer_autenticacao(): void
    {
        $this->get(route('balance-sheets.index'))->assertRedirect();
    }

    public function test_utilizador_autorizado_acede_pagina_balanco(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $user->assignRole('ADMIN');

        $this->actingAs($user)
            ->get(route('balance-sheets.index'))
            ->assertOk();
    }

    public function test_exporta_pdf_do_balanco(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $user->assignRole('ADMIN');

        $balance = app(BalanceSheetBuilder::class)->create([
            'titulo' => 'Balanço PDF',
            'data_referencia' => now()->toDateString(),
            'periodo_inicio' => now()->startOfMonth()->toDateString(),
            'periodo_fim' => now()->toDateString(),
        ], $user->id);

        $this->actingAs($user)
            ->get(route('balance-sheets.pdf', $balance))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
