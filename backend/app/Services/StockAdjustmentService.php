<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockAdjustmentService
{
    /**
     * Aplica um ajuste de stock numa localização (delta positivo ou negativo).
     *
     * @throws ValidationException
     */
    public function aplicar(
        string $productId,
        string $locationId,
        float $delta,
        ?string $note = null,
        ?string $performedBy = null,
        ?float $unitCost = null,
    ): StockMovement {
        if (abs($delta) < 0.00001) {
            throw ValidationException::withMessages([
                'delta' => [__('pages.stock_reload.adjustment_delta_required')],
            ]);
        }

        return DB::transaction(function () use ($productId, $locationId, $delta, $note, $performedBy, $unitCost) {
            $product = Product::query()->lockForUpdate()->findOrFail($productId);

            $balance = StockBalance::query()
                ->where('location_id', $locationId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            $saldoAtual = (float) ($balance?->quantity ?? 0);
            $novoSaldo = $saldoAtual + $delta;

            if ($novoSaldo < -0.00001) {
                throw ValidationException::withMessages([
                    'delta' => [
                        __('pages.stock_reload.adjustment_insufficient_stock', [
                            'current' => number_format($saldoAtual, 2, ',', '.'),
                            'delta' => number_format($delta, 2, ',', '.'),
                        ]),
                    ],
                ]);
            }

            if (! $balance) {
                $balance = StockBalance::query()->create([
                    'id' => (string) Str::uuid(),
                    'location_id' => $locationId,
                    'product_id' => $productId,
                    'quantity' => 0,
                ]);
            }

            $balance->quantity = max(0, $novoSaldo);
            $balance->save();

            $custo = $unitCost ?? (float) $product->preco_compra;
            $referenciaId = (string) Str::uuid();
            $quantidadeAbsoluta = abs($delta);

            $movement = StockMovement::query()->create([
                'id' => (string) Str::uuid(),
                'product_id' => $productId,
                'from_location_id' => $delta < 0 ? $locationId : null,
                'to_location_id' => $delta > 0 ? $locationId : null,
                'type' => 'ADJUSTMENT',
                'quantity' => $quantidadeAbsoluta,
                'unit_cost' => $custo,
                'reference_type' => 'STOCK_ADJUSTMENT',
                'reference_id' => $referenciaId,
                'note' => $note ?: __('pages.stock_reload.adjustment_default_note', [
                    'delta' => number_format($delta, 2, ',', '.'),
                ]),
                'performed_by' => $performedBy,
            ]);

            $product->stock = (float) StockBalance::query()
                ->where('product_id', $productId)
                ->sum('quantity');
            $product->save();

            return $movement;
        });
    }
}
