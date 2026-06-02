<?php

namespace App\Models;

use App\Support\SaleItemTaxSnapshot;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'sale_id',
        'produto_id',
        'nome',
        'quantidade',
        'preco_venda',
        'preco_sem_iva',
        'iva_percentual',
        'valor_iva_unitario',
        'subtotal',
    ];

    protected $casts = [
        'quantidade' => 'decimal:2',
        'preco_venda' => 'decimal:2',
        'preco_sem_iva' => 'decimal:2',
        'iva_percentual' => 'decimal:2',
        'valor_iva_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'produto_id');
    }

    /**
     * @return array{precoVenda: float, precoSemIva: float, ivaPercentual: float, valorIvaUnitario: float}
     */
    public function resolvedTax(): array
    {
        $product = $this->relationLoaded('product') ? $this->product : null;

        return SaleItemTaxSnapshot::fromPayload([
            'precoVenda' => $this->preco_venda,
            'precoSemIva' => $this->preco_sem_iva,
            'ivaPercentual' => $this->iva_percentual,
            'valorIvaUnitario' => $this->valor_iva_unitario,
        ], $product);
    }

    public function ivaTotalLinha(): float
    {
        $tax = $this->resolvedTax();
        $quantidade = (float) $this->quantidade;
        if ($quantidade <= 0) {
            return 0.0;
        }

        if ($tax['valorIvaUnitario'] > 0) {
            return round($tax['valorIvaUnitario'] * $quantidade, 2);
        }

        $subtotal = (float) $this->subtotal;
        $ivaPercentual = (float) $tax['ivaPercentual'];
        if ($ivaPercentual <= 0 || $subtotal <= 0) {
            return 0.0;
        }

        return round($subtotal - ($subtotal / (1 + ($ivaPercentual / 100))), 2);
    }
}
