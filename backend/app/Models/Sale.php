<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'referencia',
        'register_id',
        'source_location_id',
        'customer_id',
        'user_id',
        'cliente',
        'caixa',
        'operador',
        'metodo_pagamento',
        'estado',
        'subtotal',
        'desconto_aplicado',
        'total',
        'valor_pago',
        'troco',
        'data',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'desconto_aplicado' => 'decimal:2',
        'total' => 'decimal:2',
        'valor_pago' => 'decimal:2',
        'troco' => 'decimal:2',
        'data' => 'datetime',
    ];

    public function itens()
    {
        return $this->hasMany(SaleItem::class);
    }
}
