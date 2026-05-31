<?php

namespace Tests\Feature\Admin;

use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleReversalRequest;
use App\Models\User;
use App\Services\ReversalReportBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReversalReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('reversals.view', 'web');
        Permission::findOrCreate('reversals.manage', 'web');
        $role = Role::findOrCreate('ADMIN', 'web');
        $role->givePermissionTo(['reversals.view', 'reversals.manage']);
    }

    public function test_agrupa_reversoes_por_intervalos_diarios(): void
    {
        $register = Register::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'CX-REV',
            'name' => 'Caixa Reversões',
            'is_active' => true,
        ]);

        $vendaAprovada = $this->criarVenda($register, 1500, now()->subDays(2));
        $vendaPendente = $this->criarVenda($register, 800, now()->subDay());

        $this->criarReversao($vendaAprovada, 'APPROVED', now()->subDays(2), now()->subDays(1));
        $this->criarReversao($vendaPendente, 'PENDING', now()->subDay());

        $relatorio = app(ReversalReportBuilder::class)->build(
            Carbon::now()->startOfMonth(),
            Carbon::now(),
        );

        $this->assertEquals('daily', $relatorio['tipo_intervalo']);
        $this->assertEquals(2, $relatorio['totais']['total']);
        $this->assertEquals(1, $relatorio['totais']['aprovadas']);
        $this->assertEquals(1, $relatorio['totais']['pendentes']);
        $this->assertEquals(1500.0, $relatorio['totais']['valor_revertido']);
        $this->assertNotEmpty($relatorio['intervalos']);
    }

    public function test_pagina_reversoes_requer_autenticacao(): void
    {
        $this->get(route('reversals.index'))->assertRedirect();
    }

    public function test_utilizador_autorizado_acede_pagina(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $user->assignRole('ADMIN');
        $user->givePermissionTo('reversals.view');

        $this->actingAs($user)
            ->get(route('reversals.index'))
            ->assertOk();
    }

    public function test_exporta_pdf_do_relatorio(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $user->assignRole('ADMIN');
        $user->givePermissionTo('reversals.view');

        $this->actingAs($user)
            ->get(route('reversals.pdf', [
                'periodo_inicio' => now()->startOfMonth()->toDateString(),
                'periodo_fim' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function criarVenda(Register $register, float $total, Carbon $data): Sale
    {
        return Sale::query()->create([
            'id' => (string) Str::uuid(),
            'referencia' => 'VD-REV-'.Str::upper(Str::random(4)),
            'register_id' => $register->id,
            'operador' => 'Operador Teste',
            'cliente' => 'Cliente Geral',
            'caixa' => $register->name,
            'metodo_pagamento' => 'Dinheiro',
            'estado' => 'Concluida',
            'subtotal' => $total,
            'total' => $total,
            'valor_pago' => $total,
            'data' => $data,
        ]);
    }

    private function criarReversao(Sale $sale, string $status, Carbon $requestedAt, ?Carbon $decidedAt = null): SaleReversalRequest
    {
        return SaleReversalRequest::query()->create([
            'id' => (string) Str::uuid(),
            'sale_id' => $sale->id,
            'status' => $status,
            'reason' => 'Teste de reversão',
            'requested_at' => $requestedAt,
            'decided_at' => $decidedAt,
        ]);
    }
}
