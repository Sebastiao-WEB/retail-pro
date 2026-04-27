<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StockTransferItem extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'stock_transfer_id',
        'product_id',
        'product_name_snapshot',
        'quantity_requested',
        'quantity_sent',
        'quantity_received',
    ];

    protected $casts = [
        'quantity_requested' => 'decimal:2',
        'quantity_sent' => 'decimal:2',
        'quantity_received' => 'decimal:2',
    ];

    public function transfer()
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }
}
