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
     */
    public static function quantidade(
        Product $product,
        ?string $locationId,
        Collection|array $saldoLocalPorProduto = [],
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

        return $global;
    }

    /**
     * Stock que a venda vai realmente consumir (saldo no local; senão global se não houver saldos).
     */
    public static function quantidadeParaVenda(Product $product, string $locationId, ?StockBalance $balance = null): float
    {
        $global = (float) $product->stock;

        if ($balance) {
            $local = (float) $balance->quantity;

            return $local > 0 ? $local : max(0.0, $global);
        }

        if ($global <= 0) {
            return 0.0;
        }

        if (StockBalance::query()->where('product_id', $product->id)->exists()) {
            return 0.0;
        }

        return $global;
    }

    /**
     * Garante saldo no local de venda alinhado com products.stock (fonte do admin/POS).
     *
     * @param  float  $quantidadeNecessaria  Quantidade total a debitar neste local (0 = só garantir linha)
     */
    public static function resolverSaldoParaVenda(
        string $locationId,
        string $productId,
        float $quantidadeNecessaria = 0.0,
    ): ?StockBalance {
        $produto = Product::query()->whereKey($productId)->lockForUpdate()->first();
        if (! $produto) {
            return null;
        }

        $global = (float) $produto->stock;
        if ($global <= 0) {
            return null;
        }

        $balance = StockBalance::query()
            ->where('location_id', $locationId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            return StockBalance::query()->create([
                'id' => (string) Str::uuid(),
                'location_id' => $locationId,
                'product_id' => $productId,
                'quantity' => $global,
            ]);
        }

        $local = (float) $balance->quantity;
        $necessario = $quantidadeNecessaria > 0 ? $quantidadeNecessaria : $local;

        if ($local < $necessario && $global >= $necessario) {
            $balance->quantity = $global;
            $balance->save();
        } elseif ($local <= 0 && $global > 0) {
            $balance->quantity = $global;
            $balance->save();
        }

        return $balance;
    }

    /** @deprecated Use resolverSaldoParaVenda() */
    public static function garantirSaldoLocalParaVenda(string $locationId, string $productId): ?StockBalance
    {
        return self::resolverSaldoParaVenda($locationId, $productId);
    }
}
