<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'product_id',
        'from_location_id',
        'to_location_id',
        'type',
        'quantity',
        'unit_cost',
        'reference_type',
        'reference_id',
        'note',
        'performed_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function fromLocation()
    {
        return $this->belongsTo(StockLocation::class, 'from_location_id');
    }

    public function toLocation()
    {
        return $this->belongsTo(StockLocation::class, 'to_location_id');
    }

    public function reloadRecord()
    {
        return $this->belongsTo(Purchase::class, 'reference_id');
    }

    public function scopeStockReloads($query)
    {
        return $query
            ->where('type', 'IN')
            ->whereIn('reference_type', ['PURCHASE', 'STOCK_RELOAD']);
    }

    public function scopeToActiveLocations(Builder $query): Builder
    {
        return $query->whereHas('toLocation', fn (Builder $locationQuery) => $locationQuery->active());
    }
}
