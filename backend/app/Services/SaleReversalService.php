<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReversalRequest;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Support\ProductStockDisplay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SaleReversalService
{
    public function approve(SaleReversalRequest $pedido, ?string $reason = null, ?string $approvedBy = null): SaleReversalRequest
    {
        if ($pedido->status !== 'PENDING') {
            throw new InvalidArgumentException('A solicitação já foi decidida.');
        }

        return DB::transaction(function () use ($pedido, $reason, $approvedBy) {
            /** @var Sale|null $venda */
            $venda = Sale::query()->with('itens')->lockForUpdate()->find($pedido->sale_id);
            if (! $venda) {
                throw new InvalidArgumentException('Venda associada não encontrada.');
            }

            if (strcasecmp((string) $venda->estado, 'Revertida') === 0) {
                $pedido->update([
                    'status' => 'APPROVED',
                    'approved_by' => $approvedBy,
                    'reason' => $reason !== null && $reason !== '' ? $reason : $pedido->reason,
                    'decided_at' => $pedido->decided_at ?? now(),
                ]);

                return $pedido->fresh();
            }

            $this->estornarStockDaVenda($venda, $approvedBy);

            $venda->update(['estado' => 'Revertida']);

            $pedido->update([
                'status' => 'APPROVED',
                'approved_by' => $approvedBy,
                'reason' => $reason !== null && $reason !== '' ? $reason : $pedido->reason,
                'decided_at' => now(),
            ]);

            return $pedido->fresh();
        });
    }

    public function reject(SaleReversalRequest $pedido, ?string $reason = null, ?string $approvedBy = null): SaleReversalRequest
    {
        if ($pedido->status !== 'PENDING') {
            throw new InvalidArgumentException('A solicitação já foi decidida.');
        }

        $pedido->update([
            'status' => 'REJECTED',
            'approved_by' => $approvedBy,
            'reason' => $reason !== null && $reason !== '' ? $reason : $pedido->reason,
            'decided_at' => now(),
        ]);

        return $pedido->fresh();
    }

    private function estornarStockDaVenda(Sale $venda, ?string $performedBy): void
    {
        $locationId = $venda->source_location_id;
        if (! $locationId) {
            return;
        }

        $produtosAfetados = [];

        foreach ($venda->itens as $item) {
            $produtoId = $item->produto_id;
            if (! $produtoId) {
                continue;
            }

            $produto = Product::query()->find($produtoId);
            if (! $produto || ! ProductStockDisplay::controlaEstoque($produto)) {
                continue;
            }

            $quantidade = (float) $item->quantidade;
            if ($quantidade <= 0) {
                continue;
            }

            $balance = StockBalance::query()
                ->where('location_id', $locationId)
                ->where('product_id', $produtoId)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                $balance = StockBalance::query()->create([
                    'id' => (string) Str::uuid(),
                    'location_id' => $locationId,
                    'product_id' => $produtoId,
                    'quantity' => 0,
                ]);
            }

            $balance->quantity = (float) $balance->quantity + $quantidade;
            $balance->save();

            StockMovement::query()->create([
                'id' => (string) Str::uuid(),
                'product_id' => $produtoId,
                'from_location_id' => null,
                'to_location_id' => $locationId,
                'type' => 'RETURN',
                'quantity' => $quantidade,
                'unit_cost' => (float) ($item->preco_sem_iva ?: $item->preco_venda ?: 0),
                'reference_type' => 'SALE_REVERSAL',
                'reference_id' => $venda->id,
                'note' => 'Estorno por reversão da venda '.$venda->referencia,
                'performed_by' => $performedBy,
            ]);

            $produtosAfetados[$produtoId] = true;
        }

        foreach (array_keys($produtosAfetados) as $produtoId) {
            $produto = Product::query()->find($produtoId);
            if (! $produto) {
                continue;
            }

            ProductStockDisplay::sincronizarStockGlobal($produtoId);
        }
    }
}
