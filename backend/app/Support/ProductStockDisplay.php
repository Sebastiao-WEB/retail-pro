<?php

namespace App\Support;

use App\Models\Product;
use App\Models\StockBalance;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Stock efectivo para POS/API: saldo do local quando existir;
 * caso contrário, o stock global do produto (o mesmo que o admin lista após recargas).
 */
final class ProductStockDisplay
{
    /**
     * @param  Collection<string, float>|array<string, float>  $saldoLocalPorProduto  quantity por product_id no local pedido
     * @param  Collection<string>|array<int, string>  $produtosComSaldoEmQualquerLocal
     */
    public static function quantidade(
        Product $product,
        ?string $locationId,
        Collection|array $saldoLocalPorProduto = [],
        Collection|array $produtosComSaldoEmQualquerLocal = [],
    ): float {
        $global = (float) $product->stock;

        if ($locationId === null || $locationId === '') {
            return $global;
        }

        $mapaLocal = $saldoLocalPorProduto instanceof Collection
            ? $saldoLocalPorProduto->all()
            : $saldoLocalPorProduto;

        if (array_key_exists($product->id, $mapaLocal)) {
            return (float) $mapaLocal[$product->id];
        }

        $comSaldo = $produtosComSaldoEmQualquerLocal instanceof Collection
            ? $produtosComSaldoEmQualquerLocal->contains($product->id)
            : in_array($product->id, $produtosComSaldoEmQualquerLocal, true);

        if ($comSaldo) {
            return 0.0;
        }

        return $global;
    }

    public static function produtosComSaldoEmQualquerLocal(Collection $productIds): Collection
    {
        if ($productIds->isEmpty()) {
            return collect();
        }

        return StockBalance::query()
            ->whereIn('product_id', $productIds)
            ->distinct()
            ->pluck('product_id');
    }

    /**
     * Garante linha em stock_balances para baixar inventário quando só existe stock global.
     */
    public static function garantirSaldoLocalParaVenda(string $locationId, string $productId): ?StockBalance
    {
        $balance = StockBalance::query()
            ->where('location_id', $locationId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if ($balance) {
            return $balance;
        }

        $produto = Product::query()->whereKey($productId)->lockForUpdate()->first();
        if (! $produto || (float) $produto->stock <= 0) {
            return null;
        }

        if (StockBalance::query()->where('product_id', $productId)->exists()) {
            return null;
        }

        return StockBalance::query()->create([
            'id' => (string) Str::uuid(),
            'location_id' => $locationId,
            'product_id' => $productId,
            'quantity' => (float) $produto->stock,
        ]);
    }
}
