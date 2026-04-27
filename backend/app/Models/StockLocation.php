<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StockLocation extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'register_id',
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

    public function register()
    {
        return $this->belongsTo(Register::class);
    }
}
