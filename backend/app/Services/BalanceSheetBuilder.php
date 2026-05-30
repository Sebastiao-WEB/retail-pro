<?php

namespace App\Services;

use App\Models\BalanceSheet;
use App\Models\BalanceSheetLine;
use App\Models\CashSession;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockBalance;
use Carbon\Carbon;
use Illuminate\Support\Str;

class BalanceSheetBuilder
{
    public function create(array $dados, ?string $userId = null): BalanceSheet
    {
        $dataReferencia = Carbon::parse($dados['data_referencia']);
        $periodoInicio = isset($dados['periodo_inicio']) ? Carbon::parse($dados['periodo_inicio']) : $dataReferencia->copy()->startOfYear();
        $periodoFim = isset($dados['periodo_fim']) ? Carbon::parse($dados['periodo_fim']) : $dataReferencia;

        $balance = BalanceSheet::query()->create([
            'id' => (string) Str::uuid(),
            'referencia' => $this->gerarReferencia($dataReferencia),
            'titulo' => $dados['titulo'] ?? ('Balanço em '.$dataReferencia->format('d/m/Y')),
            'data_referencia' => $dataReferencia,
            'periodo_inicio' => $periodoInicio,
            'periodo_fim' => $periodoFim,
            'status' => 'DRAFT',
            'notas' => $dados['notas'] ?? null,
            'prepared_by' => $userId,
        ]);

        $this->syncAutomaticLines($balance);
        $balance->recalculateTotals();

        return $balance->fresh(['lines', 'preparedBy']);
    }

    public function syncAutomaticLines(BalanceSheet $balance): void
    {
        $dataReferencia = Carbon::parse($balance->data_referencia)->endOfDay();
        $periodoInicio = Carbon::parse($balance->periodo_inicio ?? $balance->data_referencia)->startOfDay();
        $periodoFim = Carbon::parse($balance->periodo_fim ?? $balance->data_referencia)->endOfDay();

        $definicoes = [
            ['secao' => 'ACTIVO', 'grupo' => 'CIRCULANTE', 'rubrika' => 'Caixa e equivalentes', 'valor' => $this->calcularCaixa($dataReferencia), 'ordem' => 10],
            ['secao' => 'ACTIVO', 'grupo' => 'CIRCULANTE', 'rubrika' => 'Inventários (stock)', 'valor' => $this->calcularValorStock(), 'ordem' => 20],
            ['secao' => 'ACTIVO', 'grupo' => 'CIRCULANTE', 'rubrika' => 'Contas a receber', 'valor' => 0, 'ordem' => 30],
            ['secao' => 'ACTIVO', 'grupo' => 'NAO_CIRCULANTE', 'rubrika' => 'Activos fixos tangíveis', 'valor' => 0, 'ordem' => 40],
            ['secao' => 'PASSIVO', 'grupo' => 'CIRCULANTE', 'rubrika' => 'Contas a pagar', 'valor' => 0, 'ordem' => 110],
            ['secao' => 'PASSIVO', 'grupo' => 'CIRCULANTE', 'rubrika' => 'Outros passivos circulantes', 'valor' => 0, 'ordem' => 120],
            ['secao' => 'PASSIVO', 'grupo' => 'NAO_CIRCULANTE', 'rubrika' => 'Empréstimos e financiamentos', 'valor' => 0, 'ordem' => 130],
            ['secao' => 'CAPITAL', 'grupo' => null, 'rubrika' => 'Capital social', 'valor' => 0, 'ordem' => 210],
            ['secao' => 'CAPITAL', 'grupo' => null, 'rubrika' => 'Resultados transitados', 'valor' => 0, 'ordem' => 220],
            ['secao' => 'CAPITAL', 'grupo' => null, 'rubrika' => 'Resultado do exercício', 'valor' => $this->calcularResultadoExercicio($periodoInicio, $periodoFim), 'ordem' => 230],
        ];

        foreach ($definicoes as $item) {
            BalanceSheetLine::query()->updateOrCreate(
                [
                    'balance_sheet_id' => $balance->id,
                    'secao' => $item['secao'],
                    'rubrika' => $item['rubrika'],
                    'automatico' => true,
                ],
                [
                    'grupo' => $item['grupo'],
                    'valor' => $item['valor'],
                    'ordem' => $item['ordem'],
                ]
            );
        }

        $balance->load('lines');
    }

    public function calcularValorStock(): float
    {
        return (float) StockBalance::query()
            ->join('products', 'products.id', '=', 'stock_balances.product_id')
            ->selectRaw('COALESCE(SUM(stock_balances.quantity * COALESCE(NULLIF(products.preco_compra, 0), products.preco_venda)), 0) as total')
            ->value('total');
    }

    public function calcularCaixa(Carbon $dataReferencia): float
    {
        $fechos = (float) CashSession::query()
            ->where('status', 'CLOSED')
            ->where('closed_at', '<=', $dataReferencia)
            ->sum('closing_balance');

        $aberturas = (float) CashSession::query()
            ->where('status', 'OPEN')
            ->where('opened_at', '<=', $dataReferencia)
            ->sum('opening_balance');

        return $fechos + $aberturas;
    }

    public function calcularResultadoExercicio(Carbon $inicio, Carbon $fim): float
    {
        $vendas = (float) Sale::query()
            ->whereBetween('data', [$inicio, $fim])
            ->where('estado', '!=', 'Revertida')
            ->sum('total');

        $custo = (float) SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.produto_id')
            ->whereBetween('sales.data', [$inicio, $fim])
            ->where('sales.estado', '!=', 'Revertida')
            ->selectRaw('COALESCE(SUM(sale_items.quantidade * COALESCE(NULLIF(products.preco_compra, 0), products.preco_venda)), 0) as total')
            ->value('total');

        return $vendas - $custo;
    }

    private function gerarReferencia(Carbon $data): string
    {
        $base = 'BAL-'.$data->format('Ymd');
        $sequencia = BalanceSheet::query()->where('referencia', 'like', $base.'%')->count() + 1;

        return sprintf('%s-%02d', $base, $sequencia);
    }
}
