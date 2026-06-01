<?php

namespace App\Support;

use Illuminate\Validation\Rule;

final class ProductValidation
{
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
        $regras = [
            'nullable',
            'string',
            'max:255',
            Rule::unique('products', 'codigo_barras')->ignore($ignorarProductId),
        ];

        if ($sometimes) {
            array_unshift($regras, 'sometimes');
        }

        return $regras;
    }
}
