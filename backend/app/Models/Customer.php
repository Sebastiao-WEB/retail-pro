<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'nome', 'telefone', 'email', 'nuit', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
