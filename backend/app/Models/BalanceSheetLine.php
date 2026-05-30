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
        'secao',
        'grupo',
        'rubrika',
        'valor',
        'automatico',
        'ordem',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'automatico' => 'boolean',
    ];

    public function balanceSheet(): BelongsTo
    {
        return $this->belongsTo(BalanceSheet::class);
    }
}
