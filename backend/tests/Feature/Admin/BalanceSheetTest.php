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
            'name' => 'Loja Balanço',
            'type' => 'STORE_FLOOR',
            'is_saleable' => true,
            'is_active' => true,
        ]);
        $location->registers()->sync([$register->id]);

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
        $this->assertSame(1, $balance->locationLines()->count());
        $this->assertEquals(5.0, (float) $balance->locationLines()->first()->quantity);
    }

    public function test_balanco_ignora_stock_em_localizacao_inactiva(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $user->assignRole('ADMIN');

        $register = Register::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'CX-BAL-2',
            'name' => 'Caixa Balanço 2',
            'is_active' => true,
        ]);

        $localActivo = StockLocation::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'LOC-ACT',
            'name' => 'Loja Activa',
            'type' => 'STORE_FLOOR',
            'is_saleable' => true,
            'is_active' => true,
        ]);
        $localActivo->registers()->sync([$register->id]);

        $localInactivo = StockLocation::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'LOC-OFF',
            'name' => 'Loja Desactivada',
            'type' => 'WAREHOUSE',
            'is_saleable' => false,
            'is_active' => false,
        ]);
        $localInactivo->registers()->sync([$register->id]);

        $product = Product::query()->create([
            'id' => (string) Str::uuid(),
            'nome' => 'Produto Local Inactivo',
            'codigo_barras' => '7777777777777',
            'preco_compra' => 8,
            'preco_venda' => 12,
            'iva_tipo' => 'ISENTO',
            'iva_valor' => 0,
            'iva_percentual' => 0,
            'stock' => 15,
            'is_active' => true,
        ]);

        StockBalance::query()->create([
            'id' => (string) Str::uuid(),
            'location_id' => $localActivo->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        StockBalance::query()->create([
            'id' => (string) Str::uuid(),
            'location_id' => $localInactivo->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $balance = app(BalanceSheetBuilder::class)->create([
            'titulo' => 'Balanço Locais',
            'data_referencia' => now()->toDateString(),
            'periodo_inicio' => now()->startOfMonth()->toDateString(),
            'periodo_fim' => now()->toDateString(),
        ], $user->id);

        $line = $balance->lines()->first();

        $this->assertNotNull($line);
        $this->assertEquals(5.0, (float) $line->qtd_stock);
        $this->assertEquals(40.0, (float) $line->valor_stock_compra);
        $this->assertEquals(60.0, (float) $line->valor_stock_venda);
        $this->assertEquals(5.0, (float) $balance->total_stock_qtd);
        $this->assertSame(1, $balance->locationLines()->count());
        $this->assertEquals($localActivo->id, $balance->locationLines()->first()->location_id);
    }

    public function test_balanco_ignora_recargas_para_localizacao_inactiva(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $user->assignRole('ADMIN');

        $register = Register::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'CX-BAL-3',
            'name' => 'Caixa Balanço 3',
            'is_active' => true,
        ]);

        $localInactivo = StockLocation::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'LOC-OFF-2',
            'name' => 'Armaz?m Desactivado',
            'type' => 'WAREHOUSE',
            'is_saleable' => false,
            'is_active' => false,
        ]);
        $localInactivo->registers()->sync([$register->id]);

        $product = Product::query()->create([
            'id' => (string) Str::uuid(),
            'nome' => 'Produto Só Local Inactivo',
            'codigo_barras' => '6666666666666',
            'preco_compra' => 5,
            'preco_venda' => 8,
            'iva_tipo' => 'ISENTO',
            'iva_valor' => 0,
            'iva_percentual' => 0,
            'stock' => 12,
            'is_active' => true,
        ]);

        StockMovement::query()->create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'to_location_id' => $localInactivo->id,
            'type' => 'IN',
            'quantity' => 12,
            'unit_cost' => 5,
            'reference_type' => 'STOCK_RELOAD',
            'reference_id' => (string) Str::uuid(),
            'created_at' => now(),
        ]);

        StockBalance::query()->create([
            'id' => (string) Str::uuid(),
            'location_id' => $localInactivo->id,
            'product_id' => $product->id,
            'quantity' => 12,
        ]);

        $balance = app(BalanceSheetBuilder::class)->create([
            'titulo' => 'Balanço Recarga Inactiva',
            'data_referencia' => now()->toDateString(),
            'periodo_inicio' => now()->startOfMonth()->toDateString(),
            'periodo_fim' => now()->toDateString(),
        ], $user->id);

        $this->assertSame(0, $balance->lines()->count());
        $this->assertSame(0, $balance->locationLines()->count());
        $this->assertEquals(0.0, (float) $balance->total_recargas_valor);
        $this->assertEquals(0.0, (float) $balance->total_stock_qtd);
    }

    public function test_listagem_balancos_do_mais_recente_para_o_mais_antigo(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $user->assignRole('ADMIN');

        $builder = app(BalanceSheetBuilder::class);

        $antigo = $builder->create([
            'titulo' => 'Balanço Antigo',
            'data_referencia' => '2025-01-31',
            'periodo_inicio' => '2025-01-01',
            'periodo_fim' => '2025-01-31',
        ], $user->id);

        $recente = $builder->create([
            'titulo' => 'Balanço Recente',
            'data_referencia' => '2025-03-31',
            'periodo_inicio' => '2025-03-01',
            'periodo_fim' => '2025-03-31',
        ], $user->id);

        $response = $this->actingAs($user)->get(route('balance-sheets.index'));

        $response->assertOk();
        $response->assertSeeInOrder([
            $recente->referencia,
            $antigo->referencia,
        ]);
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
