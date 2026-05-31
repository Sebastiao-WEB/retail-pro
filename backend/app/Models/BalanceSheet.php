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
        'total_recargas_qtd',
        'total_recargas_valor',
        'total_vendas_qtd',
        'total_vendas_valor',
        'total_custo_vendas',
        'total_lucro',
        'total_stock_qtd',
        'total_stock_valor_compra',
        'total_stock_valor_venda',
    ];

    protected $casts = [
        'data_referencia' => 'date',
        'periodo_inicio' => 'date',
        'periodo_fim' => 'date',
        'finalized_at' => 'datetime',
        'total_recargas_qtd' => 'decimal:2',
        'total_recargas_valor' => 'decimal:2',
        'total_vendas_qtd' => 'decimal:2',
        'total_vendas_valor' => 'decimal:2',
        'total_custo_vendas' => 'decimal:2',
        'total_lucro' => 'decimal:2',
        'total_stock_qtd' => 'decimal:2',
        'total_stock_valor_compra' => 'decimal:2',
        'total_stock_valor_venda' => 'decimal:2',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(BalanceSheetLine::class)->orderBy('ordem');
    }

    public function locationLines(): HasMany
    {
        return $this->hasMany(BalanceSheetLocationLine::class)->orderBy('ordem');
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

        $this->total_recargas_qtd = (float) $lines->sum('qtd_recarregada');
        $this->total_recargas_valor = (float) $lines->sum('valor_recarga');
        $this->total_vendas_qtd = (float) $lines->sum('qtd_vendida');
        $this->total_vendas_valor = (float) $lines->sum('valor_vendas');
        $this->total_custo_vendas = (float) $lines->sum('custo_vendas');
        $this->total_lucro = (float) $lines->sum('lucro');
        $this->total_stock_qtd = (float) $lines->sum('qtd_stock');
        $this->total_stock_valor_compra = (float) $lines->sum('valor_stock_compra');
        $this->total_stock_valor_venda = (float) $lines->sum('valor_stock_venda');
        $this->save();
    }
}
