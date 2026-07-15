<?php

namespace App\Support;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockLocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stock efectivo para POS/API: saldo do local quando existir;
 * caso contrário, o stock global do produto (o mesmo que o admin lista após recargas).
 */
final class ProductStockDisplay
{
    public static function controlaEstoque(Product $product): bool
    {
        return $product->controlaEstoque();
    }

    public static function somaStockLocaisActivos(string $productId): float
    {
        return (float) StockBalance::query()
            ->where('product_id', $productId)
            ->inActiveLocations()
            ->sum('quantity');
    }

    public static function sincronizarStockGlobal(string $productId): void
    {
        Product::query()->whereKey($productId)->update([
            'stock' => self::somaStockLocaisActivos($productId),
        ]);
    }

    public static function sincronizarStockGlobalDosProdutosDaLocalizacao(string $locationId): void
    {
        StockBalance::query()
            ->where('location_id', $locationId)
            ->distinct()
            ->pluck('product_id')
            ->each(fn (string $productId) => self::sincronizarStockGlobal($productId));
    }

    public static function stockParaExibicao(Product $product): float
    {
        $temSaldos = StockBalance::query()->where('product_id', $product->id)->exists();

        if (! $temSaldos) {
            return (float) $product->stock;
        }

        if (isset($product->stock_activo)) {
            return (float) ($product->stock_activo ?? 0);
        }

        return self::somaStockLocaisActivos($product->id);
    }

    public static function localizacaoEstaActiva(string $locationId): bool
    {
        return StockLocation::query()
            ->whereKey($locationId)
            ->active()
            ->exists();
    }

    public static function exigirLocalizacaoActiva(string $locationId, string $campo = 'location_id'): void
    {
        if (! self::localizacaoEstaActiva($locationId)) {
            throw ValidationException::withMessages([
                $campo => [__('validation.stock_location_inactive')],
            ]);
        }
    }

    /**
     * @param  Collection<string, float>|array<string, float>  $saldoLocalPorProduto  quantity por product_id no local pedido
     */
    public static function quantidade(
        Product $product,
        ?string $locationId,
        Collection|array $saldoLocalPorProduto = [],
    ): float {
        $global = self::stockParaExibicao($product);

        if ($locationId === null || $locationId === '') {
            return $global;
        }

        if (! self::localizacaoEstaActiva($locationId)) {
            return 0.0;
        }

        $mapaLocal = $saldoLocalPorProduto instanceof Collection
            ? $saldoLocalPorProduto->all()
            : $saldoLocalPorProduto;

        if (array_key_exists($product->id, $mapaLocal)) {
            return (float) $mapaLocal[$product->id];
        }

        return 0.0;
    }

    /**
     * Stock que a venda vai realmente consumir (saldo no local; senão global se não houver saldos).
     */
    public static function quantidadeParaVenda(Product $product, string $locationId, ?StockBalance $balance = null): float
    {
        if (! self::localizacaoEstaActiva($locationId)) {
            return 0.0;
        }

        $global = self::stockParaExibicao($product);

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
        if (! self::localizacaoEstaActiva($locationId)) {
            return null;
        }

        $produto = Product::query()->whereKey($productId)->lockForUpdate()->first();
        if (! $produto) {
            return null;
        }

        $global = self::stockParaExibicao($produto);
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
