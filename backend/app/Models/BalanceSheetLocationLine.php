<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceSheetLocationLine extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'balance_sheet_id',
        'product_id',
        'location_id',
        'local_codigo',
        'local_nome',
        'produto_nome',
        'codigo_barras',
        'quantity',
        'valor_compra',
        'valor_venda',
        'ordem',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'valor_compra' => 'decimal:2',
        'valor_venda' => 'decimal:2',
    ];

    public function balanceSheet(): BelongsTo
    {
        return $this->belongsTo(BalanceSheet::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'location_id');
    }
}
