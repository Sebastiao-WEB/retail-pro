<?php

namespace App\Models;

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
}
