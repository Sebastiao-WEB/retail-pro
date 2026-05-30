<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BalanceSheet extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'referencia',
        'titulo',
        'data_referencia',
        'periodo_inicio',
        'periodo_fim',
        'status',
        'notas',
        'prepared_by',
        'finalized_at',
        'total_activo',
        'total_passivo',
        'total_capital_proprio',
    ];

    protected $casts = [
        'data_referencia' => 'date',
        'periodo_inicio' => 'date',
        'periodo_fim' => 'date',
        'finalized_at' => 'datetime',
        'total_activo' => 'decimal:2',
        'total_passivo' => 'decimal:2',
        'total_capital_proprio' => 'decimal:2',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(BalanceSheetLine::class)->orderBy('ordem');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function isFinalized(): bool
    {
        return $this->status === 'FINALIZED';
    }

    public function recalculateTotals(): void
    {
        $lines = $this->lines;

        $this->total_activo = (float) $lines->where('secao', 'ACTIVO')->sum('valor');
        $this->total_passivo = (float) $lines->where('secao', 'PASSIVO')->sum('valor');
        $this->total_capital_proprio = (float) $lines->where('secao', 'CAPITAL')->sum('valor');
        $this->save();
    }

    public function isBalanced(): bool
    {
        return abs($this->total_activo - ($this->total_passivo + $this->total_capital_proprio)) < 0.01;
    }
}
