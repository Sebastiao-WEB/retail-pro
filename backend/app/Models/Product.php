<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasUuids;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'nome',
        'codigo_barras',
        'categoria',
        'unidade_venda',
        'preco_compra',
        'preco_venda',
        'iva_tipo',
        'iva_valor',
        'iva_percentual',
        'stock',
        'controla_estoque',
        'is_active',
    ];

    protected $casts = [
        'preco_compra' => 'decimal:2',
        'preco_venda' => 'decimal:2',
        'iva_valor' => 'decimal:2',
        'iva_percentual' => 'decimal:2',
        'stock' => 'decimal:2',
        'controla_estoque' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function controlaEstoque(): bool
    {
        return (bool) ($this->controla_estoque ?? true);
    }

    public function stockBalances()
    {
        return $this->hasMany(StockBalance::class, 'product_id');
    }
}
