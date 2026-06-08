<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StockLocation extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'code',
        'name',
        'type',
        'is_saleable',
        'is_active',
    ];

    protected $casts = [
        'is_saleable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function registers(): BelongsToMany
    {
        return $this->belongsToMany(Register::class, 'register_stock_location')
            ->withTimestamps();
    }

    public function balances()
    {
        return $this->hasMany(StockBalance::class, 'location_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function registerCodesLabel(): string
    {
        if ($this->relationLoaded('registers')) {
            $codes = $this->registers->pluck('code')->filter()->sort()->values();

            return $codes->isNotEmpty() ? $codes->join(', ') : '—';
        }

        return '—';
    }
}
