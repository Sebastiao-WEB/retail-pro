<?php

namespace Tests\Feature\Admin;

use App\Models\CashSession;
use App\Models\Product;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ApiTestHelpers;
use Tests\TestCase;

class AdminPagesSmokeTest extends TestCase
{
    use ApiTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PermissionCatalog::sync();
        Role::findOrCreate('ADMIN', 'web')->syncPermissions(PermissionCatalog::allNames());
    }

    public function test_todas_paginas_admin_carregam_para_utilizador_com_permissoes(): void
    {
        $ambiente = $this->criarAmbienteApi();

        $admin = User::factory()->create(['role' => 'ADMIN']);
        $admin->assignRole('ADMIN');
        $admin->syncPermissions(PermissionCatalog::allNames());

        $sale = Sale::query()->create([
            'id' => (string) Str::uuid(),
            'referencia' => 'VD-SMOKE-001',
            'register_id' => $ambiente['register']->id,
            'user_id' => $ambiente['user']->id,
            'operador' => $ambiente['user']->name,
            'cliente' => 'Cliente Geral',
            'caixa' => $ambiente['register']->name,
            'metodo_pagamento' => 'Dinheiro',
            'estado' => 'Concluida',
            'subtotal' => 100,
            'total' => 100,
            'valor_pago' => 100,
            'data' => now(),
        ]);

        SaleItem::query()->create([
            'id' => (string) Str::uuid(),
            'sale_id' => $sale->id,
            'produto_id' => $ambiente['product']->id,
            'nome' => $ambiente['product']->nome,
            'quantidade' => 1,
            'preco_venda' => 100,
            'preco_sem_iva' => 100,
            'iva_percentual' => 0,
            'valor_iva_unitario' => 0,
            'subtotal' => 100,
        ]);

        $cashSession = CashSession::query()->create([
            'id' => (string) Str::uuid(),
            'register_id' => $ambiente['register']->id,
            'user_id' => $ambiente['user']->id,
            'status' => 'CLOSED',
            'opening_balance' => 1000,
            'closing_balance' => 1100,
            'difference_amount' => 100,
            'opened_at' => now()->subHours(8),
            'closed_at' => now()->subHour(),
            'report_snapshot' => [
                'utilizador' => $ambiente['user']->name,
                'caixa' => $ambiente['register']->name,
            ],
        ]);

        $pages = [
            'dashboard',
            'products.index',
            'customers.index',
            'sales.index',
            'balance-sheets.index',
            'operator-reports.index',
            'cash-sessions.active',
            'cash-sessions.closed',
            'reversals.index',
            'registers.index',
            'stock-locations.index',
            'stock.reload',
            'stock.movements',
            'stock.transfers',
            'settings.company',
            'security.settings',
            'users.index',
            'roles.permissions',
        ];

        foreach ($pages as $routeName) {
            $this->actingAs($admin)
                ->get(route($routeName))
                ->assertOk("Falha ao carregar {$routeName}");
        }

        $this->actingAs($admin)
            ->get(route('sales.detail', ['sale' => $sale]))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('cash-sessions.detail', ['cashSession' => $cashSession]))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('users.edit', ['user' => $ambiente['user']]))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('stock.reload.form', ['product' => $ambiente['product']]))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('stock.reload.adjust.form', ['product' => $ambiente['product']]))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('operator-reports.detail', [
                'operador' => 'user:'.$ambiente['user']->id,
                'periodo_inicio' => now()->startOfMonth()->toDateString(),
                'periodo_fim' => now()->toDateString(),
            ]))
            ->assertOk();
    }
}
