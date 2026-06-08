<?php

namespace Tests\Feature\Console;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Services\OrphanStockBalanceMigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MigrateOrphanStockBalancesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sem_orfaos_nao_altera_nada(): void
    {
        StockLocation::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'LOC-ARM-CENTRAL',
            'name' => 'Armazém Central',
            'type' => 'WAREHOUSE',
            'is_saleable' => false,
            'is_active' => true,
        ]);

        $service = app(OrphanStockBalanceMigrationService::class);

        $this->assertEmpty($service->localizacoesOrfas());

        $result = $service->migrar('LOC-ARM-CENTRAL');

        $this->assertSame(0, $result['migrated_rows']);
        $this->assertSame(0, StockMovement::query()->where('reference_type', 'ORPHAN_BALANCE_MIGRATION')->count());
    }

    public function test_comando_dry_run_lista_destino_quando_existem_orfaos(): void
    {
        StockLocation::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'LOC-ARM-CENTRAL',
            'name' => 'Armazém Central',
            'type' => 'WAREHOUSE',
            'is_saleable' => false,
            'is_active' => true,
        ]);

        $this->artisan('stock:migrate-orphan-balances', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Nenhum saldo órfão encontrado');
    }

    public function test_migracao_move_stock_para_destino_quando_orfaos_existem(): void
    {
        $warehouse = StockLocation::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'LOC-ARM-CENTRAL',
            'name' => 'Armazém Central',
            'type' => 'WAREHOUSE',
            'is_saleable' => false,
            'is_active' => true,
        ]);

        $activeLocation = StockLocation::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'LOC-CX01',
            'name' => 'Caixa 01',
            'type' => 'STORE_FLOOR',
            'is_saleable' => true,
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'id' => (string) Str::uuid(),
            'nome' => 'ACUCAR BRANCO 1KG',
            'codigo_barras' => '1111111111111',
            'preco_compra' => 86.5,
            'preco_venda' => 110,
            'iva_tipo' => 'NORMAL',
            'iva_valor' => 16,
            'iva_percentual' => 16,
            'stock' => 99,
            'is_active' => true,
        ]);

        StockBalance::query()->create([
            'id' => (string) Str::uuid(),
            'location_id' => $activeLocation->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $orphanLocationId = (string) Str::uuid();
        $orphanBalanceId = (string) Str::uuid();

        $service = $this->partialMock(OrphanStockBalanceMigrationService::class, function ($mock) use ($orphanLocationId, $orphanBalanceId, $product) {
            $mock->shouldAllowMockingProtectedMethods();
            $mock->shouldReceive('localizacoesOrfas')->andReturn(collect([$orphanLocationId]));
            $mock->shouldReceive('balancesOrfaos')->andReturn(collect([
                (object) [
                    'balance_id' => $orphanBalanceId,
                    'location_id' => $orphanLocationId,
                    'product_id' => $product->id,
                    'quantity' => '89.00',
                    'product_name' => $product->nome,
                ],
            ]));
        });

        $result = $service->migrar('LOC-ARM-CENTRAL');

        $this->assertSame(1, $result['migrated_rows']);
        $this->assertSame(89.0, $result['migrated_units']);

        $this->assertDatabaseHas('stock_balances', [
            'location_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 89,
        ]);
        $this->assertDatabaseMissing('stock_balances', [
            'id' => $orphanBalanceId,
        ]);

        $product->refresh();
        $this->assertSame(99.0, (float) $product->stock);
    }
}
