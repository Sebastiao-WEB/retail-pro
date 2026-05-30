<?php

namespace Tests\Feature\Admin;

use App\Models\BalanceSheet;
use App\Models\User;
use App\Services\BalanceSheetBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_cria_balanco_com_rubricas_automaticas(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $user->assignRole('ADMIN');

        $builder = app(BalanceSheetBuilder::class);
        $balance = $builder->create([
            'titulo' => 'Balanço Teste',
            'data_referencia' => now()->toDateString(),
            'periodo_inicio' => now()->startOfYear()->toDateString(),
            'periodo_fim' => now()->toDateString(),
        ], $user->id);

        $this->assertDatabaseHas('balance_sheets', [
            'id' => $balance->id,
            'status' => 'DRAFT',
        ]);

        $this->assertGreaterThanOrEqual(8, $balance->lines()->count());
        $this->assertTrue($balance->lines()->where('automatico', true)->exists());
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
        ], $user->id);

        $this->actingAs($user)
            ->get(route('balance-sheets.pdf', $balance))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
