<?php

namespace App\Services;

use App\Models\BalanceSheet;
use App\Models\BalanceSheetLine;
use App\Models\BalanceSheetLocationLine;
use App\Models\Product;
use App\Models\SaleItem;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BalanceSheetBuilder
{
    public function create(array $dados, ?string $userId = null): BalanceSheet
    {
        $dataReferencia = Carbon::parse($dados['data_referencia']);
        $periodoInicio = isset($dados['periodo_inicio']) ? Carbon::parse($dados['periodo_inicio']) : $dataReferencia->copy()->startOfDay();
        $periodoFim = isset($dados['periodo_fim']) ? Carbon::parse($dados['periodo_fim']) : $dataReferencia;

        $balance = BalanceSheet::query()->create([
            'id' => (string) Str::uuid(),
            'referencia' => $this->gerarReferencia($dataReferencia),
            'titulo' => $dados['titulo'] ?? ('Balanço de fecho '.$dataReferencia->format('d/m/Y')),
            'data_referencia' => $dataReferencia,
            'periodo_inicio' => $periodoInicio,
            'periodo_fim' => $periodoFim,
            'status' => 'DRAFT',
            'notas' => $dados['notas'] ?? null,
            'prepared_by' => $userId,
        ]);

        $this->syncAutomaticLines($balance);

        return $balance->fresh(['lines.product', 'locationLines', 'preparedBy']);
    }

    public function syncAutomaticLines(BalanceSheet $balance): void
    {
        $inicio = Carbon::parse($balance->periodo_inicio ?? $balance->data_referencia)->startOfDay();
        $fim = Carbon::parse($balance->periodo_fim ?? $balance->data_referencia)->endOfDay();

        $recargas = $this->recargasPorProduto($inicio, $fim);
        $vendas = $this->vendasPorProduto($inicio, $fim);
        $stock = $this->stockPorProduto();

        $productIds = $recargas->keys()
            ->merge($vendas->keys())
            ->merge($stock->keys())
            ->unique()
            ->values();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $ordem = 0;
        $lineIds = [];

        foreach ($productIds as $productId) {
            $product = $products->get($productId);
            if (! $product) {
                continue;
            }

            $recarga = $recargas->get($productId, ['qtd' => 0.0, 'valor' => 0.0]);
            $venda = $vendas->get($productId, ['qtd' => 0.0, 'valor' => 0.0, 'custo' => 0.0]);
            $stockActual = $stock->get($productId, ['qtd' => 0.0, 'valor_compra' => 0.0, 'valor_venda' => 0.0]);
            $lucro = (float) $venda['valor'] - (float) $venda['custo'];

            $line = BalanceSheetLine::query()->updateOrCreate(
                [
                    'balance_sheet_id' => $balance->id,
                    'product_id' => $productId,
                ],
                [
                    'secao' => 'PRODUTO',
                    'grupo' => null,
                    'rubrika' => $product->nome,
                    'valor' => $lucro,
                    'automatico' => true,
                    'ordem' => ++$ordem,
                    'qtd_recarregada' => $recarga['qtd'],
                    'valor_recarga' => $recarga['valor'],
                    'qtd_vendida' => $venda['qtd'],
                    'valor_vendas' => $venda['valor'],
                    'custo_vendas' => $venda['custo'],
                    'lucro' => $lucro,
                    'qtd_stock' => $stockActual['qtd'],
                    'valor_stock_compra' => $stockActual['valor_compra'],
                    'valor_stock_venda' => $stockActual['valor_venda'],
                ]
            );

            $lineIds[] = $line->id;
        }

        BalanceSheetLine::query()
            ->where('balance_sheet_id', $balance->id)
            ->whereNotIn('id', $lineIds)
            ->delete();

        $balance->load('lines');
        $this->syncLocationLines($balance);
        $balance->recalculateTotals();
    }

    public function syncLocationLines(BalanceSheet $balance): void
    {
        $balances = StockBalance::query()
            ->with(['product', 'location'])
            ->inActiveLocations()
            ->where('quantity', '>', 0)
            ->get();

        $ordem = 0;
        $lineIds = [];

        foreach ($balances as $stockBalance) {
            $product = $stockBalance->product;
            $location = $stockBalance->location;
            if (! $product || ! $location || ! $location->is_active) {
                continue;
            }

            $qtd = (float) $stockBalance->quantity;
            $custoUnit = (float) ($product->preco_compra ?: $product->preco_venda);

            $line = BalanceSheetLocationLine::query()->updateOrCreate(
                [
                    'balance_sheet_id' => $balance->id,
                    'product_id' => $product->id,
                    'location_id' => $location->id,
                ],
                [
                    'local_codigo' => $location->code,
                    'local_nome' => $location->name,
                    'produto_nome' => $product->nome,
                    'codigo_barras' => $product->codigo_barras,
                    'quantity' => $qtd,
                    'valor_compra' => $qtd * $custoUnit,
                    'valor_venda' => $qtd * (float) $product->preco_venda,
                    'ordem' => ++$ordem,
                ]
            );

            $lineIds[] = $line->id;
        }

        BalanceSheetLocationLine::query()
            ->where('balance_sheet_id', $balance->id)
            ->whereNotIn('id', $lineIds)
            ->delete();
    }

    /** @return Collection<string, array{qtd: float, valor: float}> */
    private function recargasPorProduto(Carbon $inicio, Carbon $fim): Collection
    {
        return StockMovement::query()
            ->stockReloads()
            ->toActiveLocations()
            ->whereBetween('created_at', [$inicio, $fim])
            ->selectRaw('product_id, COALESCE(SUM(quantity), 0) as qtd, COALESCE(SUM(quantity * COALESCE(unit_cost, 0)), 0) as valor')
            ->groupBy('product_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->product_id => [
                    'qtd' => (float) $row->qtd,
                    'valor' => (float) $row->valor,
                ],
            ]);
    }

    /** @return Collection<string, array{qtd: float, valor: float, custo: float}> */
    private function vendasPorProduto(Carbon $inicio, Carbon $fim): Collection
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.produto_id')
            ->whereBetween('sales.data', [$inicio, $fim])
            ->where('sales.estado', '!=', 'Revertida')
            ->selectRaw('sale_items.produto_id as product_id')
            ->selectRaw('COALESCE(SUM(sale_items.quantidade), 0) as qtd')
            ->selectRaw('COALESCE(SUM(sale_items.subtotal), 0) as valor')
            ->selectRaw('COALESCE(SUM(sale_items.quantidade * COALESCE(NULLIF(products.preco_compra, 0), products.preco_venda)), 0) as custo')
            ->groupBy('sale_items.produto_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->product_id => [
                    'qtd' => (float) $row->qtd,
                    'valor' => (float) $row->valor,
                    'custo' => (float) $row->custo,
                ],
            ]);
    }

    /** @return Collection<string, array{qtd: float, valor_compra: float, valor_venda: float}> */
    private function stockPorProduto(): Collection
    {
        return StockBalance::query()
            ->inActiveLocations()
            ->join('products', 'products.id', '=', 'stock_balances.product_id')
            ->selectRaw('stock_balances.product_id')
            ->selectRaw('COALESCE(SUM(stock_balances.quantity), 0) as qtd')
            ->selectRaw('COALESCE(SUM(stock_balances.quantity * COALESCE(NULLIF(products.preco_compra, 0), products.preco_venda)), 0) as valor_compra')
            ->selectRaw('COALESCE(SUM(stock_balances.quantity * products.preco_venda), 0) as valor_venda')
            ->groupBy('stock_balances.product_id')
            ->havingRaw('COALESCE(SUM(stock_balances.quantity), 0) > 0')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->product_id => [
                    'qtd' => (float) $row->qtd,
                    'valor_compra' => (float) $row->valor_compra,
                    'valor_venda' => (float) $row->valor_venda,
                ],
            ]);
    }

    private function gerarReferencia(Carbon $data): string
    {
        $base = 'BAL-'.$data->format('Ymd');
        $sequencia = BalanceSheet::query()->where('referencia', 'like', $base.'%')->count() + 1;

        return sprintf('%s-%02d', $base, $sequencia);
    }
}
