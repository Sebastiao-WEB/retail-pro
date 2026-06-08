<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\OperatorSalesReportBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperatorReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('operator_reports.view', 'web');
        $role = Role::findOrCreate('ADMIN', 'web');
        $role->givePermissionTo(['operator_reports.view']);
    }

    public function test_agrupa_vendas_por_operador_com_lucro(): void
    {
        $register = Register::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'CX-OP',
            'name' => 'Caixa Operadores',
            'is_active' => true,
        ]);

        $antonio = User::factory()->create(['role' => 'CASHIER', 'name' => 'António']);
        $castro = User::factory()->create(['role' => 'CASHIER', 'name' => 'Castro']);

        $productAntonio = Product::query()->create([
            'id' => (string) Str::uuid(),
            'nome' => 'Produto António',
            'codigo_barras' => '7777777777771',
            'preco_compra' => 1000,
            'preco_venda' => 1700,
            'iva_tipo' => 'ISENTO',
            'iva_valor' => 0,
            'iva_percentual' => 0,
            'stock' => 10,
            'is_active' => true,
        ]);

        $productCastro = Product::query()->create([
            'id' => (string) Str::uuid(),
            'nome' => 'Produto Castro',
            'codigo_barras' => '7777777777772',
            'preco_compra' => 3000,
            'preco_venda' => 3500,
            'iva_tipo' => 'ISENTO',
            'iva_valor' => 0,
            'iva_percentual' => 0,
            'stock' => 10,
            'is_active' => true,
        ]);

        $this->criarVenda($register, $antonio, 'António', 1700, $productAntonio);
        $this->criarVenda($register, $castro, 'Castro', 3500, $productCastro);

        $relatorio = app(OperatorSalesReportBuilder::class)->build(
            Carbon::now()->startOfMonth(),
            Carbon::now(),
        );

        $this->assertCount(2, $relatorio['operadores']);
        $this->assertEquals(5200.0, $relatorio['totais']['vendas']);
        $this->assertEquals(1200.0, $relatorio['totais']['lucro']);

        $castro = collect($relatorio['operadores'])->firstWhere('nome', 'Castro');
        $antonio = collect($relatorio['operadores'])->firstWhere('nome', 'António');

        $this->assertNotNull($castro);
        $this->assertNotNull($antonio);
        $this->assertEquals(3500.0, $castro['total_vendas']);
        $this->assertEquals(500.0, $castro['total_lucro']);
        $this->assertEquals(1700.0, $antonio['total_vendas']);
        $this->assertEquals(700.0, $antonio['total_lucro']);
        $this->assertNotEmpty($castro['vendas'][0]['itens']);
    }

    public function test_pagina_relatorio_requer_autenticacao(): void
    {
        $this->get(route('operator-reports.index'))->assertRedirect();
    }

    public function test_utilizador_autorizado_acede_relatorio(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $user->assignRole('ADMIN');
        Permission::findOrCreate('operator_reports.view', 'web');
        $user->givePermissionTo('operator_reports.view');

        $this->actingAs($user)
            ->get(route('operator-reports.index'))
            ->assertOk();
    }

    public function test_detalhe_operador_abre_pagina_dedicada(): void
    {
        $register = Register::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'CX-DET',
            'name' => 'Caixa Detalhe',
            'is_active' => true,
        ]);

        $antonio = User::factory()->create(['role' => 'CASHIER', 'name' => 'António']);
        $product = Product::query()->create([
            'id' => (string) Str::uuid(),
            'nome' => 'Produto Detalhe',
            'codigo_barras' => '8888888888881',
            'preco_compra' => 1000,
            'preco_venda' => 1700,
            'iva_tipo' => 'ISENTO',
            'iva_valor' => 0,
            'iva_percentual' => 0,
            'stock' => 10,
            'is_active' => true,
        ]);

        $sale = $this->criarVenda($register, $antonio, 'António', 1700, $product);

        $user = User::factory()->create(['role' => 'ADMIN']);
        $user->assignRole('ADMIN');
        Permission::findOrCreate('operator_reports.view', 'web');
        $user->givePermissionTo('operator_reports.view');

        $this->actingAs($user)
            ->get(route('operator-reports.detail', [
                'operador' => 'user:'.$antonio->id,
                'periodo_inicio' => now()->startOfMonth()->toDateString(),
                'periodo_fim' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('António')
            ->assertSee($sale->referencia)
            ->assertDontSee('operator-detail-modal', false);
    }

    public function test_exporta_pdf_do_relatorio(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $user->assignRole('ADMIN');
        Permission::findOrCreate('operator_reports.view', 'web');
        $user->givePermissionTo('operator_reports.view');

        $this->actingAs($user)
            ->get(route('operator-reports.pdf', [
                'periodo_inicio' => now()->startOfMonth()->toDateString(),
                'periodo_fim' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function criarVenda(Register $register, User $user, string $operador, float $total, Product $product): Sale
    {
        $sale = Sale::query()->create([
            'id' => (string) Str::uuid(),
            'referencia' => 'VD-TEST-'.Str::upper(Str::random(4)),
            'register_id' => $register->id,
            'user_id' => $user->id,
            'operador' => $operador,
            'cliente' => 'Cliente Geral',
            'caixa' => $register->name,
            'metodo_pagamento' => 'Dinheiro',
            'estado' => 'Concluida',
            'subtotal' => $total,
            'total' => $total,
            'valor_pago' => $total,
            'data' => now(),
        ]);

        SaleItem::query()->create([
            'id' => (string) Str::uuid(),
            'sale_id' => $sale->id,
            'produto_id' => $product->id,
            'nome' => $product->nome,
            'quantidade' => 1,
            'preco_venda' => $total,
            'preco_sem_iva' => $total,
            'iva_percentual' => 0,
            'valor_iva_unitario' => 0,
            'subtotal' => $total,
        ]);

        return $sale;
    }
}
