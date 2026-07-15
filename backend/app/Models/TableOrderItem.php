<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TableOrderItem extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'table_order_id',
        'product_id',
        'nome',
        'quantidade',
        'preco_venda',
        'preco_sem_iva',
        'iva_percentual',
        'valor_iva_unitario',
        'iva_tipo',
        'subtotal',
        'sort_order',
    ];

    protected $casts = [
        'quantidade' => 'decimal:3',
        'preco_venda' => 'decimal:2',
        'preco_sem_iva' => 'decimal:2',
        'iva_percentual' => 'decimal:2',
        'valor_iva_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function tableOrder()
    {
        return $this->belongsTo(TableOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
