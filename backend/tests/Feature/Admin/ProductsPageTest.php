<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ApiTestHelpers;
use Tests\TestCase;

class ProductsPageTest extends TestCase
{
    use ApiTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('products.manage', 'web');
        Permission::findOrCreate('products.view', 'web');
        $role = Role::findOrCreate('ADMIN', 'web');
        $role->givePermissionTo(['products.manage', 'products.view']);
    }

    public function test_pagina_produtos_carrega_com_tabela_e_modal(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $admin = $ambiente['user'];
        $admin->assignRole('ADMIN');
        $admin->givePermissionTo(['products.manage', 'products.view']);

        $this->actingAs($admin)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee(__('pages.products.title'))
            ->assertSee(__('pages.products.new'))
            ->assertSee('product-form-modal', false)
            ->assertDontSee('<th class="px-3 py-2">'.__('app.fields.category').'</th>', false)
            ->assertDontSee('<th class="px-3 py-2">'.__('app.fields.sale_unit').'</th>', false)
            ->assertDontSee('<th class="px-3 py-2">'.__('app.fields.iva').'</th>', false);
    }

    public function test_pagina_produtos_mostra_acoes_stock_com_permissao(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $admin = $ambiente['user'];
        $admin->assignRole('ADMIN');
        $admin->givePermissionTo(['products.manage', 'products.view', 'stock.reload']);

        $this->actingAs($admin)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee(route('stock.reload.form', [
                'product' => $ambiente['product'],
                'return_to' => 'products',
            ]), false)
            ->assertSee(route('stock.reload.adjust.form', [
                'product' => $ambiente['product'],
                'return_to' => 'products',
            ]), false)
            ->assertSee('package-plus', false)
            ->assertSee('sliders-horizontal', false);
    }

    public function test_pagina_editar_produto_mostra_formulario(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $admin = $ambiente['user'];
        $admin->assignRole('ADMIN');
        $admin->givePermissionTo(['products.manage', 'products.view']);

        $produto = $ambiente['product'];

        $this->actingAs($admin)
            ->get(route('products.edit', ['product' => $produto, 'search' => 'teste']))
            ->assertOk()
            ->assertSee($produto->nome)
            ->assertSee('product-edit-form', false)
            ->assertSee(route('products.update', $produto), false);
    }

    public function test_editar_produto_via_formulario_redireciona_para_lista(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $admin = $ambiente['user'];
        $admin->assignRole('ADMIN');
        $admin->givePermissionTo(['products.manage', 'products.view']);

        $produto = $ambiente['product'];

        $this->actingAs($admin)
            ->put(route('products.update', $produto), [
                'nome' => 'Produto Actualizado',
                'codigo_barras' => $produto->codigo_barras,
                'unidade_venda' => 'UN',
                'preco_compra' => '50',
                'preco_venda' => '100',
                'iva_tipo' => 'ISENTO',
                'is_active' => '1',
                'return_search' => 'teste',
            ])
            ->assertRedirect(route('products.index', ['search' => 'teste']));

        $this->assertSame('Produto Actualizado', $produto->fresh()->nome);
    }

    public function test_rejeita_codigo_barras_duplicado_com_validacao_no_campo(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $admin = $ambiente['user'];
        $admin->assignRole('ADMIN');
        $admin->givePermissionTo(['products.manage', 'products.view']);

        Product::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'nome' => 'Pão',
            'codigo_barras' => '1234567890123',
            'preco_compra' => 1,
            'preco_venda' => 2,
            'iva_tipo' => 'ISENTO',
            'iva_valor' => 0,
            'iva_percentual' => 0,
            'stock' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->postJson(route('products.store'), [
                'nome' => 'Bolacha',
                'codigo_barras' => '1234567890123',
                'preco_compra' => '1',
                'preco_venda' => '2',
                'iva_tipo' => 'ISENTO',
                'is_active' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['codigo_barras']);

        $this->assertDatabaseMissing('products', ['nome' => 'Bolacha']);
        $this->assertDatabaseHas('products', ['nome' => 'Pão', 'codigo_barras' => '1234567890123']);
    }

    public function test_grava_produto_vendido_por_peso(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $admin = $ambiente['user'];
        $admin->assignRole('ADMIN');
        $admin->givePermissionTo(['products.manage', 'products.view']);

        $this->actingAs($admin)
            ->postJson(route('products.store'), [
                'nome' => 'Peixe fresco',
                'unidade_venda' => 'KG',
                'preco_compra' => '200',
                'preco_venda' => '450',
                'iva_tipo' => 'ISENTO',
                'is_active' => true,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('products', [
            'nome' => 'Peixe fresco',
            'unidade_venda' => 'KG',
        ]);
    }

    public function test_permite_editar_produto_mantendo_o_mesmo_codigo_barras(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $admin = $ambiente['user'];
        $admin->assignRole('ADMIN');
        $admin->givePermissionTo(['products.manage', 'products.view']);

        $produto = Product::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'nome' => 'Pão',
            'codigo_barras' => '1234567890123',
            'preco_compra' => 1,
            'preco_venda' => 2,
            'iva_tipo' => 'ISENTO',
            'iva_valor' => 0,
            'iva_percentual' => 0,
            'stock' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->putJson(route('products.update', $produto), [
                'nome' => 'Pão especial',
                'codigo_barras' => '1234567890123',
                'unidade_venda' => 'UN',
                'preco_compra' => '1',
                'preco_venda' => '2',
                'iva_tipo' => 'ISENTO',
                'is_active' => true,
            ])
            ->assertOk();

        $this->assertSame('Pão especial', $produto->fresh()->nome);
    }
}
