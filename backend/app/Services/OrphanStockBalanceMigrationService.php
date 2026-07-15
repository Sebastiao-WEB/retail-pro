<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockBalance;
use App\Support\ProductStockDisplay;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OrphanStockBalanceMigrationService
{
    private const REFERENCE_TYPE = 'ORPHAN_BALANCE_MIGRATION';

    /** @return Collection<int, string> */
    public function localizacoesOrfas(): Collection
    {
        return DB::table('stock_balances as sb')
            ->leftJoin('stock_locations as sl', 'sl.id', '=', 'sb.location_id')
            ->whereNull('sl.id')
            ->distinct()
            ->orderBy('sb.location_id')
            ->pluck('sb.location_id');
    }

    /**
     * @return array{
     *     orphan_location_ids: list<string>,
     *     destination: array{id: string, code: string, name: string}|null,
     *     rows_total: int,
     *     rows_with_stock: int,
     *     units_to_migrate: float,
     *     products_affected: int,
     *     sample: list<array{product_id: string, product_name: string, orphan_location_id: string, quantity: float}>,
     *     stock_drift: list<array{product_id: string, product_name: string, products_stock: float, balances_sum: float, drift: float}>
     * }
     */
    public function preview(string $destinationCode = 'LOC-ARM-CENTRAL'): array
    {
        $destination = $this->resolverDestino($destinationCode);
        $orphans = $this->balancesOrfaos();
        $rowsWithStock = $orphans->filter(fn ($row) => (float) $row->quantity > 0);

        $sample = $rowsWithStock
            ->sortByDesc('quantity')
            ->take(15)
            ->map(fn ($row) => [
                'product_id' => $row->product_id,
                'product_name' => $row->product_name,
                'orphan_location_id' => $row->location_id,
                'quantity' => (float) $row->quantity,
            ])
            ->values()
            ->all();

        return [
            'orphan_location_ids' => $this->localizacoesOrfas()->values()->all(),
            'destination' => [
                'id' => $destination->id,
                'code' => $destination->code,
                'name' => $destination->name,
            ],
            'rows_total' => $orphans->count(),
            'rows_with_stock' => $rowsWithStock->count(),
            'units_to_migrate' => (float) $rowsWithStock->sum('quantity'),
            'products_affected' => $rowsWithStock->pluck('product_id')->unique()->count(),
            'sample' => $sample,
            'stock_drift' => $this->detectarDesviosStock(),
        ];
    }

    /**
     * @return array{
     *     migrated_rows: int,
     *     migrated_units: float,
     *     deleted_empty_rows: int,
     *     products_recalculated: int,
     *     orphan_location_ids: list<string>,
     *     destination_code: string
     * }
     */
    public function migrar(string $destinationCode = 'LOC-ARM-CENTRAL', ?string $performedBy = null): array
    {
        $destination = $this->resolverDestino($destinationCode);
        $orphans = $this->balancesOrfaos();

        if ($orphans->isEmpty()) {
            return [
                'migrated_rows' => 0,
                'migrated_units' => 0.0,
                'deleted_empty_rows' => 0,
                'products_recalculated' => 0,
                'orphan_location_ids' => [],
                'destination_code' => $destination->code,
            ];
        }

        $batchReferenceId = (string) Str::uuid();
        $migratedRows = 0;
        $migratedUnits = 0.0;
        $deletedEmptyRows = 0;
        $productIds = [];

        DB::transaction(function () use (
            $orphans,
            $destination,
            $performedBy,
            $batchReferenceId,
            &$migratedRows,
            &$migratedUnits,
            &$deletedEmptyRows,
            &$productIds,
        ) {
            foreach ($orphans as $orphan) {
                $quantity = (float) $orphan->quantity;
                $productIds[] = $orphan->product_id;

                if ($quantity > 0) {
                    $product = Product::query()->lockForUpdate()->findOrFail($orphan->product_id);

                    $destinationBalance = StockBalance::query()
                        ->where('location_id', $destination->id)
                        ->where('product_id', $orphan->product_id)
                        ->lockForUpdate()
                        ->first();

                    if (! $destinationBalance) {
                        $destinationBalance = StockBalance::query()->create([
                            'id' => (string) Str::uuid(),
                            'location_id' => $destination->id,
                            'product_id' => $orphan->product_id,
                            'quantity' => 0,
                        ]);
                    }

                    $destinationBalance->quantity = (float) $destinationBalance->quantity + $quantity;
                    $destinationBalance->save();

                    StockMovement::query()->create([
                        'id' => (string) Str::uuid(),
                        'product_id' => $orphan->product_id,
                        'from_location_id' => null,
                        'to_location_id' => $destination->id,
                        'type' => 'ADJUSTMENT',
                        'quantity' => $quantity,
                        'unit_cost' => (float) ($product->preco_compra ?: $product->preco_venda),
                        'reference_type' => self::REFERENCE_TYPE,
                        'reference_id' => $batchReferenceId,
                        'note' => sprintf(
                            'Migração de stock órfão (localização apagada %s) para %s.',
                            $orphan->location_id,
                            $destination->code
                        ),
                        'performed_by' => $performedBy,
                    ]);

                    $migratedRows++;
                    $migratedUnits += $quantity;
                } else {
                    $deletedEmptyRows++;
                }

                StockBalance::query()->whereKey($orphan->balance_id)->delete();
            }

            foreach (array_unique($productIds) as $productId) {
                $this->recalcularStockGlobal($productId);
            }
        });

        return [
            'migrated_rows' => $migratedRows,
            'migrated_units' => $migratedUnits,
            'deleted_empty_rows' => $deletedEmptyRows,
            'products_recalculated' => count(array_unique($productIds)),
            'orphan_location_ids' => $this->localizacoesOrfas()->values()->all(),
            'destination_code' => $destination->code,
        ];
    }

    /** @return list<array{product_id: string, product_name: string, products_stock: float, balances_sum: float, drift: float}> */
    public function detectarDesviosStock(): array
    {
        return DB::table('products as p')
            ->leftJoin('stock_balances as sb', 'sb.product_id', '=', 'p.id')
            ->leftJoin('stock_locations as sl', 'sl.id', '=', 'sb.location_id')
            ->where('p.is_active', true)
            ->groupBy('p.id', 'p.nome', 'p.stock')
            ->selectRaw('p.id as product_id')
            ->selectRaw('p.nome as product_name')
            ->selectRaw('CAST(p.stock AS DECIMAL(14,2)) as products_stock')
            ->selectRaw('COALESCE(SUM(CASE WHEN sl.id IS NOT NULL THEN sb.quantity ELSE 0 END), 0) as active_balances_sum')
            ->selectRaw('COALESCE(SUM(sb.quantity), 0) as all_balances_sum')
            ->havingRaw('ABS(CAST(p.stock AS DECIMAL(14,2)) - COALESCE(SUM(sb.quantity), 0)) > 0.009')
            ->orderByDesc(DB::raw('ABS(CAST(p.stock AS DECIMAL(14,2)) - COALESCE(SUM(sb.quantity), 0))'))
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'product_id' => $row->product_id,
                'product_name' => $row->product_name,
                'products_stock' => (float) $row->products_stock,
                'balances_sum' => (float) $row->all_balances_sum,
                'active_balances_sum' => (float) $row->active_balances_sum,
                'drift' => round((float) $row->products_stock - (float) $row->all_balances_sum, 2),
            ])
            ->values()
            ->all();
    }

    public function recalcularStockGlobal(string $productId): void
    {
        ProductStockDisplay::sincronizarStockGlobal($productId);
    }

    /** @return list<array{sale_id: string, referencia: string, product_name: string, quantity: float, data: string}> */
    public function vendasSuspeitas(float $minQuantity = 100): array
    {
        return DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->join('products as p', 'p.id', '=', 'si.produto_id')
            ->where('si.quantidade', '>=', $minQuantity)
            ->where('s.estado', 'Concluida')
            ->orderByDesc('si.quantidade')
            ->limit(10)
            ->get([
                's.id as sale_id',
                's.referencia',
                'p.nome as product_name',
                'si.quantidade as quantity',
                's.data',
            ])
            ->map(fn ($row) => [
                'sale_id' => $row->sale_id,
                'referencia' => $row->referencia,
                'product_name' => $row->product_name,
                'quantity' => (float) $row->quantity,
                'data' => (string) $row->data,
            ])
            ->all();
    }

    private function resolverDestino(string $destinationCode): StockLocation
    {
        $destination = StockLocation::query()
            ->where('code', $destinationCode)
            ->where('is_active', true)
            ->first();

        if (! $destination) {
            throw new RuntimeException("Localização de destino '{$destinationCode}' não encontrada ou inactiva.");
        }

        return $destination;
    }

    /** @return Collection<int, object{balance_id: string, location_id: string, product_id: string, quantity: string, product_name: string}> */
    protected function balancesOrfaos(): Collection
    {
        return DB::table('stock_balances as sb')
            ->leftJoin('stock_locations as sl', 'sl.id', '=', 'sb.location_id')
            ->join('products as p', 'p.id', '=', 'sb.product_id')
            ->whereNull('sl.id')
            ->orderBy('sb.location_id')
            ->orderBy('p.nome')
            ->get([
                'sb.id as balance_id',
                'sb.location_id',
                'sb.product_id',
                'sb.quantity',
                'p.nome as product_name',
            ]);
    }
}
