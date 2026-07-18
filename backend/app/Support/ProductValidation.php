<?php

namespace App\Support;

use Illuminate\Validation\Rule;

final class ProductValidation
{
    public const UNIDADE_VENDA_UN = 'UN';

    public const UNIDADE_VENDA_KG = 'KG';

    public static function normalizarUnidadeVenda(?string $valor): string
    {
        return strtoupper(trim((string) $valor)) === self::UNIDADE_VENDA_KG
            ? self::UNIDADE_VENDA_KG
            : self::UNIDADE_VENDA_UN;
    }

    /**
     * @return array<int, mixed>
     */
    public static function regrasUnidadeVenda(bool $sometimes = false): array
    {
        $regras = ['string', 'in:'.self::UNIDADE_VENDA_UN.','.self::UNIDADE_VENDA_KG];
        if ($sometimes) {
            array_unshift($regras, 'sometimes');
        } else {
            array_unshift($regras, 'nullable');
        }

        return $regras;
    }

    public static function normalizarCodigoBarras(?string $valor): ?string
    {
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    /**
     * @return array<int, mixed>
     */
    public static function regrasCodigoBarras(?string $ignorarProductId = null, bool $sometimes = false): array
    {
        $unique = Rule::unique('products', 'codigo_barras')
            ->whereNull('deleted_at')
            ->ignore($ignorarProductId);

        $regras = [
            'nullable',
            'string',
            'max:255',
            $unique,
        ];

        if ($sometimes) {
            array_unshift($regras, 'sometimes');
        }

        return $regras;
    }
}
