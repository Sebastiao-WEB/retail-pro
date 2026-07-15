<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Register extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'code', 'name', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function stockLocations(): BelongsToMany
    {
        return $this->belongsToMany(StockLocation::class, 'register_stock_location')
            ->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'register_user');
    }

    public function cashSessions()
    {
        return $this->hasMany(CashSession::class);
    }

    public function getSourceLocationAttribute(): ?StockLocation
    {
        if ($this->relationLoaded('stockLocations')) {
            return $this->stockLocations
                ->where('is_active', true)
                ->sortBy('code')
                ->first();
        }

        return $this->stockLocations()
            ->where('stock_locations.is_active', true)
            ->orderBy('stock_locations.code')
            ->first();
    }
}
