<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\ProductsPage;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

        Livewire::actingAs($admin)
            ->test(ProductsPage::class)
            ->call('openCreateModal')
            ->set('nome', 'Bolacha')
            ->set('codigo_barras', '1234567890123')
            ->set('preco_compra', '1')
            ->set('preco_venda', '2')
            ->call('save')
            ->assertHasErrors(['codigo_barras']);

        $this->assertDatabaseMissing('products', ['nome' => 'Bolacha']);
        $this->assertDatabaseHas('products', ['nome' => 'Pão', 'codigo_barras' => '1234567890123']);
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

        Livewire::actingAs($admin)
            ->test(ProductsPage::class)
            ->call('openEditModal', $produto->id)
            ->set('nome', 'Pão especial')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Pão especial', $produto->fresh()->nome);
    }
}
