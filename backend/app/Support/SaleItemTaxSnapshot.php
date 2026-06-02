<?php

namespace App\Support;

use App\Models\Product;

final class SaleItemTaxSnapshot
{
    private static function snapshotPossuiIvaValido(
        float $precoVenda,
        float $precoSemIva,
        float $ivaPercentual,
        float $valorIvaUnitario
    ): bool {
        if ($ivaPercentual > 0 || $valorIvaUnitario > 0) {
            return true;
        }

        return $precoSemIva > 0 && ($precoVenda - $precoSemIva) > 0.004;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{precoVenda: float, precoSemIva: float, ivaPercentual: float, valorIvaUnitario: float}
     */
    public static function fromPayload(array $item, ?Product $product = null): array
    {
        $precoVenda = (float) ($item['precoVenda'] ?? $item['preco_venda'] ?? 0);
        $precoSemIva = (float) ($item['precoSemIva'] ?? $item['preco_sem_iva'] ?? 0);
        $ivaPercentual = (float) ($item['ivaPercentual'] ?? $item['iva_percentual'] ?? 0);
        $valorIvaUnitario = (float) ($item['valorIvaUnitario'] ?? $item['valor_iva_unitario'] ?? 0);

        if (
            ! self::snapshotPossuiIvaValido($precoVenda, $precoSemIva, $ivaPercentual, $valorIvaUnitario)
            && $product instanceof Product
            && $precoVenda > 0
        ) {
            $derivado = self::fromProductPrice($precoVenda, $product);
            $precoSemIva = $derivado['precoSemIva'];
            $ivaPercentual = $derivado['ivaPercentual'];
            $valorIvaUnitario = $derivado['valorIvaUnitario'];
        }

        if ($ivaPercentual <= 0 && $valorIvaUnitario > 0 && $precoSemIva > 0) {
            $ivaPercentual = round(($valorIvaUnitario / $precoSemIva) * 100, 2);
        } elseif ($ivaPercentual <= 0 && $precoVenda > $precoSemIva && $precoSemIva > 0) {
            $ivaPercentual = round((($precoVenda - $precoSemIva) / $precoSemIva) * 100, 2);
            if ($valorIvaUnitario <= 0) {
                $valorIvaUnitario = round($precoVenda - $precoSemIva, 2);
            }
        } elseif ($ivaPercentual > 0 && $precoSemIva <= 0 && $precoVenda > 0) {
            $precoSemIva = round($precoVenda / (1 + ($ivaPercentual / 100)), 2);
            if ($valorIvaUnitario <= 0) {
                $valorIvaUnitario = round($precoVenda - $precoSemIva, 2);
            }
        }

        return [
            'precoVenda' => $precoVenda,
            'precoSemIva' => $precoSemIva,
            'ivaPercentual' => $ivaPercentual,
            'valorIvaUnitario' => $valorIvaUnitario,
        ];
    }

    /**
     * @return array{precoSemIva: float, ivaPercentual: float, valorIvaUnitario: float}
     */
    public static function fromProductPrice(float $precoVendaComIva, Product $product): array
    {
        $tipo = strtoupper((string) $product->iva_tipo);

        if ($tipo === 'ISENTO' || $precoVendaComIva <= 0) {
            return [
                'precoSemIva' => $precoVendaComIva,
                'ivaPercentual' => 0.0,
                'valorIvaUnitario' => 0.0,
            ];
        }

        if ($tipo === 'MONETARIO') {
            $valorIvaUnitario = round((float) $product->iva_valor, 2);
            $precoSemIva = max(0, round($precoVendaComIva - $valorIvaUnitario, 2));
            $ivaPercentual = $precoSemIva > 0
                ? round(($valorIvaUnitario / $precoSemIva) * 100, 2)
                : 0.0;

            return compact('precoSemIva', 'ivaPercentual', 'valorIvaUnitario');
        }

        $taxa = min(100.0, max(0.0, (float) ($product->iva_percentual ?: $product->iva_valor)));
        $precoSemIva = $taxa > 0
            ? round($precoVendaComIva / (1 + ($taxa / 100)), 2)
            : $precoVendaComIva;
        $valorIvaUnitario = round($precoVendaComIva - $precoSemIva, 2);

        return [
            'precoSemIva' => $precoSemIva,
            'ivaPercentual' => $taxa,
            'valorIvaUnitario' => $valorIvaUnitario,
        ];
    }

    public static function ivaTipoProduto(?Product $product): string
    {
        if (! $product instanceof Product) {
            return 'isento';
        }

        return match (strtoupper((string) $product->iva_tipo)) {
            'MONETARIO' => 'monetario',
            'PERCENTUAL' => 'percentual',
            default => 'isento',
        };
    }
}
