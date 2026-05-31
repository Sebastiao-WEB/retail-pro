<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceSheetLine extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'balance_sheet_id',
        'product_id',
        'secao',
        'grupo',
        'rubrika',
        'valor',
        'automatico',
        'ordem',
        'qtd_recarregada',
        'valor_recarga',
        'qtd_vendida',
        'valor_vendas',
        'custo_vendas',
        'lucro',
        'qtd_stock',
        'valor_stock_compra',
        'valor_stock_venda',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'automatico' => 'boolean',
        'qtd_recarregada' => 'decimal:2',
        'valor_recarga' => 'decimal:2',
        'qtd_vendida' => 'decimal:2',
        'valor_vendas' => 'decimal:2',
        'custo_vendas' => 'decimal:2',
        'lucro' => 'decimal:2',
        'qtd_stock' => 'decimal:2',
        'valor_stock_compra' => 'decimal:2',
        'valor_stock_venda' => 'decimal:2',
    ];

    public function balanceSheet(): BelongsTo
    {
        return $this->belongsTo(BalanceSheet::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
