<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\ProductStockDisplay;
use App\Support\StoreFloorLocationResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ConsolidateStoreFloorStockService
{
    private const REFERENCE_TYPE = 'STORE_FLOOR_CONSOLIDATION';

    /**
     * @return array{
     *     store_floor: array{id: string, code: string, name: string},
     *     source_codes: list<string>,
     *     products_merged: int,
     *     units_merged: float,
     *     source_rows_cleared: int
     * }
     */
    public function preview(): array
    {
        $storeFloor = StoreFloorLocationResolver::findSharedStoreFloor()
            ?? new StockLocation([
                'code' => StoreFloorLocationResolver::CODE,
                'name' => StoreFloorLocationResolver::NAME,
            ]);

        $sources = $this->localizacoesOrigem();
        $merges = $this->calcularFusoes($sources, $storeFloor->id ?? 'pending');

        return [
            'store_floor' => [
                'id' => $storeFloor->id ?? '',
                'code' => StoreFloorLocationResolver::CODE,
                'name' => StoreFloorLocationResolver::NAME,
            ],
            'source_codes' => $sources->pluck('code')->all(),
            'products_merged' => $merges->count(),
            'units_merged' => (float) $merges->sum('total_from_sources'),
            'source_rows_cleared' => $this->contarLinhasOrigem($sources),
        ];
    }

    /**
     * @return array{
     *     store_floor_code: string,
     *     products_merged: int,
     *     units_merged: float,
     *     source_rows_cleared: int,
     *     users_updated: int,
     *     legacy_locations_updated: int
     * }
     */
    public function consolidar(?string $performedBy = null): array
    {
        $storeFloor = StoreFloorLocationResolver::ensureExists();
        $sources = $this->localizacoesOrigem();

        if ($sources->isEmpty()) {
            throw new RuntimeException('Nenhuma localização de origem (LOC-CX01/LOC-CX02) encontrada.');
        }

        $merges = $this->calcularFusoes($sources, $storeFloor->id);
        $batchReferenceId = (string) Str::uuid();
        $productIds = [];

        DB::transaction(function () use ($merges, $storeFloor, $sources, $performedBy, $batchReferenceId, &$productIds) {
            foreach ($merges as $merge) {
                if ((float) $merge['total_from_sources'] <= 0) {
                    continue;
                }

                $productIds[] = $merge['product_id'];
                $product = Product::query()->lockForUpdate()->findOrFail($merge['product_id']);

                $destination = StockBalance::query()
                    ->where('location_id', $storeFloor->id)
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                if (! $destination) {
                    $destination = StockBalance::query()->create([
                        'id' => (string) Str::uuid(),
                        'location_id' => $storeFloor->id,
                        'product_id' => $product->id,
                        'quantity' => 0,
                    ]);
                }

                foreach ($merge['sources'] as $sourceRow) {
                    $qty = (float) $sourceRow['quantity'];
                    if ($qty <= 0) {
                        continue;
                    }

                    StockMovement::query()->create([
                        'id' => (string) Str::uuid(),
                        'product_id' => $product->id,
                        'from_location_id' => $sourceRow['location_id'],
                        'to_location_id' => $storeFloor->id,
                        'type' => 'TRANSFER',
                        'quantity' => $qty,
                        'unit_cost' => (float) ($product->preco_compra ?: $product->preco_venda),
                        'reference_type' => self::REFERENCE_TYPE,
                        'reference_id' => $batchReferenceId,
                        'note' => sprintf(
                            'Consolidação piso de loja: %s → %s.',
                            $sourceRow['code'],
                            $storeFloor->code
                        ),
                        'performed_by' => $performedBy,
                    ]);
                }

                $destination->quantity = (float) $destination->quantity + (float) $merge['total_from_sources'];
                $destination->save();
            }

            foreach ($sources as $source) {
                StockBalance::query()->where('location_id', $source->id)->delete();
            }

            foreach (array_unique($productIds) as $productId) {
                ProductStockDisplay::sincronizarStockGlobal($productId);
            }

            StockLocation::query()
                ->whereIn('id', $sources->pluck('id'))
                ->update(['is_saleable' => false]);

            foreach ($sources as $source) {
                $source->registers()->detach();
            }
        });

        $usersUpdated = User::query()->update(['source_location_id' => $storeFloor->id]);

        return [
            'store_floor_code' => $storeFloor->code,
            'products_merged' => $merges->filter(fn ($row) => (float) $row['total_from_sources'] > 0)->count(),
            'units_merged' => (float) $merges->sum('total_from_sources'),
            'source_rows_cleared' => $this->contarLinhasOrigem($sources),
            'users_updated' => $usersUpdated,
            'legacy_locations_updated' => $sources->count(),
        ];
    }

    /** @return Collection<int, StockLocation> */
    private function localizacoesOrigem(): Collection
    {
        return StockLocation::query()
            ->whereIn('code', StoreFloorLocationResolver::LEGACY_REGISTER_LOCATION_CODES)
            ->orderBy('code')
            ->get();
    }

    /** @return Collection<int, array{product_id: string, product_name: string, total_from_sources: float, sources: list<array{location_id: string, code: string, quantity: float}>}> */
    private function calcularFusoes(Collection $sources, string $storeFloorId): Collection
    {
        if ($sources->isEmpty()) {
            return collect();
        }

        $sourceIds = $sources->pluck('id')->all();
        $codeById = $sources->pluck('code', 'id');

        $rows = DB::table('stock_balances as sb')
            ->join('products as p', 'p.id', '=', 'sb.product_id')
            ->whereIn('sb.location_id', $sourceIds)
            ->where('sb.quantity', '>', 0)
            ->get(['sb.product_id', 'sb.location_id', 'sb.quantity', 'p.nome as product_name']);

        return $rows
            ->groupBy('product_id')
            ->map(function (Collection $items, string $productId) use ($codeById) {
                $sources = $items->map(fn ($row) => [
                    'location_id' => $row->location_id,
                    'code' => (string) ($codeById[$row->location_id] ?? '—'),
                    'quantity' => (float) $row->quantity,
                ])->values()->all();

                return [
                    'product_id' => $productId,
                    'product_name' => (string) $items->first()->product_name,
                    'total_from_sources' => (float) collect($sources)->sum('quantity'),
                    'sources' => $sources,
                ];
            })
            ->values();
    }

    /** @param  Collection<int, StockLocation>  $sources */
    private function contarLinhasOrigem(Collection $sources): int
    {
        return (int) StockBalance::query()
            ->whereIn('location_id', $sources->pluck('id'))
            ->count();
    }
}
