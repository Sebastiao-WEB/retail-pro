<?php

namespace App\Services;

use App\Models\StockBalance;
use App\Models\StockLocation;
use Illuminate\Support\Collection;

class StockByLocationService
{
    /** @return list<array{location_id: string, codigo: string, nome: string, tipo: string, total_qtd: float, total_valor_compra: float, total_valor_venda: float, itens: list<array<string, mixed>>}> */
    public function resumoPorLocalizacao(?string $locationId = null): array
    {
        $balances = StockBalance::query()
            ->with(['product', 'location'])
            ->where('quantity', '>', 0)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->get();

        return $this->agruparPorLocalizacao($balances);
    }

    /** @return list<array{location_id: string, codigo: string, nome: string, itens: list<array<string, mixed>>}> */
    public function detalhePorProduto(): array
    {
        $balances = StockBalance::query()
            ->with(['product', 'location'])
            ->where('quantity', '>', 0)
            ->get()
            ->groupBy('product_id');

        $resultado = [];

        foreach ($balances as $productId => $items) {
            $product = $items->first()?->product;
            if (! $product) {
                continue;
            }

            $porLocal = $items->map(function (StockBalance $balance) use ($product) {
                $qtd = (float) $balance->quantity;
                $custoUnit = (float) ($product->preco_compra ?: $product->preco_venda);

                return [
                    'location_id' => $balance->location_id,
                    'local_codigo' => $balance->location?->code ?? '—',
                    'local_nome' => $balance->location?->name ?? '—',
                    'quantity' => $qtd,
                    'valor_compra' => $qtd * $custoUnit,
                    'valor_venda' => $qtd * (float) $product->preco_venda,
                ];
            })->sortBy('local_codigo')->values()->all();

            $resultado[] = [
                'product_id' => $productId,
                'produto_nome' => $product->nome,
                'codigo_barras' => $product->codigo_barras,
                'total_qtd' => (float) collect($porLocal)->sum('quantity'),
                'locais' => $porLocal,
            ];
        }

        return collect($resultado)->sortBy('produto_nome')->values()->all();
    }

    public function quantidadeDisponivel(string $locationId, string $productId): float
    {
        return (float) StockBalance::query()
            ->where('location_id', $locationId)
            ->where('product_id', $productId)
            ->value('quantity');
    }

    /** @param Collection<int, StockBalance> $balances */
    private function agruparPorLocalizacao(Collection $balances): array
    {
        return $balances
            ->groupBy('location_id')
            ->map(function (Collection $items, string $locationId) {
                $location = $items->first()?->location;

                $itens = $items->map(function (StockBalance $balance) {
                    $product = $balance->product;
                    $qtd = (float) $balance->quantity;
                    $custoUnit = $product ? (float) ($product->preco_compra ?: $product->preco_venda) : 0.0;

                    return [
                        'product_id' => $balance->product_id,
                        'produto_nome' => $product?->nome ?? '—',
                        'codigo_barras' => $product?->codigo_barras,
                        'quantity' => $qtd,
                        'valor_compra' => $qtd * $custoUnit,
                        'valor_venda' => $product ? $qtd * (float) $product->preco_venda : 0.0,
                    ];
                })->sortBy('produto_nome')->values()->all();

                return [
                    'location_id' => $locationId,
                    'codigo' => $location?->code ?? '—',
                    'nome' => $location?->name ?? '—',
                    'tipo' => $location?->type ?? '—',
                    'total_qtd' => (float) collect($itens)->sum('quantity'),
                    'total_valor_compra' => (float) collect($itens)->sum('valor_compra'),
                    'total_valor_venda' => (float) collect($itens)->sum('valor_venda'),
                    'itens' => $itens,
                ];
            })
            ->sortBy('codigo')
            ->values()
            ->all();
    }

    /** @return list<StockLocation> */
    public function localizacoesActivas(): Collection
    {
        return StockLocation::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);
    }
}
