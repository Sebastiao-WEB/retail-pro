<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OperatorSalesReportBuilder
{
    /** @return array{
     *     periodo_inicio: Carbon,
     *     periodo_fim: Carbon,
     *     register_id: ?string,
     *     totais: array{vendas: float, custo: float, lucro: float, num_vendas: int},
     *     operadores: list<array{
     *         chave: string,
     *         nome: string,
     *         user_id: ?string,
     *         total_vendas: float,
     *         total_custo: float,
     *         total_lucro: float,
     *         num_vendas: int,
     *         vendas: list<array<string, mixed>>
     *     }>
     * }
     */
    public function build(Carbon $inicio, Carbon $fim, ?string $registerId = null): array
    {
        $inicio = $inicio->copy()->startOfDay();
        $fim = $fim->copy()->endOfDay();

        $sales = Sale::query()
            ->with(['itens', 'user', 'register'])
            ->whereBetween('data', [$inicio, $fim])
            ->where('estado', '!=', 'Revertida')
            ->when($registerId, fn ($q) => $q->where('register_id', $registerId))
            ->orderBy('data')
            ->get();

        $productIds = $sales
            ->flatMap(fn (Sale $sale) => $sale->itens->pluck('produto_id'))
            ->filter()
            ->unique()
            ->values();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        /** @var array<string, array<string, mixed>> $grouped */
        $grouped = [];

        foreach ($sales as $sale) {
            $chave = $this->chaveOperador($sale);
            $nome = $sale->user?->name ?? $sale->operador ?? 'Sem operador';
            $custo = $this->custoVenda($sale, $products);
            $lucro = (float) $sale->total - $custo;

            if (! isset($grouped[$chave])) {
                $grouped[$chave] = [
                    'chave' => $chave,
                    'nome' => $nome,
                    'user_id' => $sale->user_id,
                    'total_vendas' => 0.0,
                    'total_custo' => 0.0,
                    'total_lucro' => 0.0,
                    'num_vendas' => 0,
                    'vendas' => [],
                ];
            }

            $grouped[$chave]['total_vendas'] += (float) $sale->total;
            $grouped[$chave]['total_custo'] += $custo;
            $grouped[$chave]['total_lucro'] += $lucro;
            $grouped[$chave]['num_vendas']++;

            $grouped[$chave]['vendas'][] = [
                'id' => $sale->id,
                'referencia' => $sale->referencia,
                'data' => $sale->data,
                'cliente' => $sale->cliente,
                'caixa' => $sale->caixa ?? $sale->register?->name,
                'metodo_pagamento' => $sale->metodo_pagamento,
                'total' => (float) $sale->total,
                'custo' => $custo,
                'lucro' => $lucro,
                'itens' => $sale->itens->map(function ($item) use ($products) {
                    $product = $products->get($item->produto_id);
                    $custoUnitario = $product
                        ? (float) ($product->preco_compra ?: $product->preco_venda)
                        : 0.0;

                    return [
                        'nome' => $item->nome,
                        'codigo_barras' => $product?->codigo_barras,
                        'quantidade' => (float) $item->quantidade,
                        'preco_venda' => (float) $item->preco_venda,
                        'subtotal' => (float) $item->subtotal,
                        'custo_unitario' => $custoUnitario,
                        'custo_total' => (float) $item->quantidade * $custoUnitario,
                        'lucro' => (float) $item->subtotal - ((float) $item->quantidade * $custoUnitario),
                    ];
                })->values()->all(),
            ];
        }

        $operadores = collect($grouped)
            ->sortByDesc('total_vendas')
            ->values()
            ->all();

        $totais = [
            'vendas' => (float) collect($operadores)->sum('total_vendas'),
            'custo' => (float) collect($operadores)->sum('total_custo'),
            'lucro' => (float) collect($operadores)->sum('total_lucro'),
            'num_vendas' => (int) collect($operadores)->sum('num_vendas'),
        ];

        return [
            'periodo_inicio' => $inicio,
            'periodo_fim' => $fim,
            'register_id' => $registerId,
            'totais' => $totais,
            'operadores' => $operadores,
        ];
    }

    private function chaveOperador(Sale $sale): string
    {
        if ($sale->user_id) {
            return 'user:'.$sale->user_id;
        }

        $nome = trim((string) ($sale->operador ?? ''));

        return 'nome:'.($nome !== '' ? mb_strtolower($nome) : 'sem-operador');
    }

    /** @param Collection<string, Product> $products */
    private function custoVenda(Sale $sale, Collection $products): float
    {
        return (float) $sale->itens->sum(function ($item) use ($products) {
            $product = $products->get($item->produto_id);
            $custoUnitario = $product
                ? (float) ($product->preco_compra ?: $product->preco_venda)
                : 0.0;

            return (float) $item->quantidade * $custoUnitario;
        });
    }
}
