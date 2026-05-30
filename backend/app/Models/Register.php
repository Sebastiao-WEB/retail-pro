<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Register extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'code', 'name', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function sourceLocation()
    {
        return $this->hasOne(StockLocation::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'register_user');
    }

    public function cashSessions()
    {
        return $this->hasMany(CashSession::class);
    }
}
