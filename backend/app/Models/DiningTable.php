<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DiningTable extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'code',
        'name',
        'description',
        'register_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function register()
    {
        return $this->belongsTo(Register::class);
    }

    public function openOrder()
    {
        return $this->hasOne(TableOrder::class)->where('status', 'OPEN')->latest('opened_at');
    }

    public function orders()
    {
        return $this->hasMany(TableOrder::class);
    }
}
