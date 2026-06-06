<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleReversalRequest;
use App\Models\StockMovement;
use Carbon\Carbon;

class DashboardSummaryService
{
    /** @return array{0: Carbon, 1: Carbon} */
    public function periodRange(string $period): array
    {
        $fim = now()->endOfDay();

        return match ($period) {
            'today' => [now()->startOfDay(), $fim],
            '30d' => [now()->subDays(29)->startOfDay(), $fim],
            'month' => [now()->startOfMonth(), $fim],
            default => [now()->subDays(6)->startOfDay(), $fim],
        };
    }

    /** @return array<string, mixed> */
    public function build(string $period = '7d', ?string $registerId = null): array
    {
        [$inicio, $fim] = $this->periodRange($period);

        $salesQuery = Sale::query()
            ->when($registerId, fn ($q) => $q->where('register_id', $registerId))
            ->whereBetween('data', [$inicio, $fim]);

        $totalVendasPeriodo = (float) (clone $salesQuery)->sum('total');

        $vendasPorDia = Sale::query()
            ->selectRaw('DATE(data) as dia, COALESCE(SUM(total), 0) as total')
            ->when($registerId, fn ($q) => $q->where('register_id', $registerId))
            ->whereBetween('data', [$inicio, $fim])
            ->groupBy('dia')
            ->orderBy('dia')
            ->get()
            ->keyBy('dia');

        $labelsVendas = [];
        $dadosVendas = [];
        $cursor = $inicio->copy()->startOfDay();
        $ultimoDia = $fim->copy()->startOfDay();

        while ($cursor->lte($ultimoDia)) {
            $chave = $cursor->toDateString();
            $labelsVendas[] = $cursor->format('d/m');
            $dadosVendas[] = (float) ($vendasPorDia[$chave]->total ?? 0);
            $cursor->addDay();
        }

        $metodosPagamento = Sale::query()
            ->selectRaw('metodo_pagamento, COUNT(*) as total, COALESCE(SUM(total), 0) as valor')
            ->when($registerId, fn ($q) => $q->where('register_id', $registerId))
            ->whereBetween('data', [$inicio, $fim])
            ->groupBy('metodo_pagamento')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'metodo' => $row->metodo_pagamento ?: 'N/A',
                'quantidade' => (int) $row->total,
                'valor' => (float) $row->valor,
            ])
            ->values()
            ->all();

        return [
            'period' => $period,
            'registerId' => $registerId,
            'metrics' => [
                'totalVendasPeriodo' => $totalVendasPeriodo,
                'totalProdutos' => Product::query()->where('is_active', true)->count(),
                'totalClientes' => Customer::query()->where('is_active', true)->count(),
                'recargasMes' => (int) StockMovement::query()
                    ->stockReloads()
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->count(),
                'reversoesPendentes' => SaleReversalRequest::query()->where('status', 'PENDING')->count(),
                'caixasAtivos' => Register::query()->where('is_active', true)->count(),
            ],
            'charts' => [
                'vendasPorDia' => [
                    'labels' => $labelsVendas,
                    'values' => $dadosVendas,
                ],
                'metodosPagamento' => $metodosPagamento,
            ],
        ];
    }

    /** @return array{items: array<int, array<string, mixed>>, meta: array<string, int>} */
    public function recentSales(?string $registerId, int $page = 1, int $perPage = 5): array
    {
        $perPage = min(20, max(1, $perPage));

        $paginado = Sale::query()
            ->when($registerId, fn ($q) => $q->where('register_id', $registerId))
            ->latest('created_at')
            ->latest('data')
            ->paginate(
                $perPage,
                [
                    'id',
                    'referencia',
                    'cliente',
                    'caixa',
                    'operador',
                    'metodo_pagamento',
                    'total',
                    'estado',
                    'data',
                    'created_at',
                ],
                'page',
                max(1, $page)
            );

        return [
            'items' => collect($paginado->items())
                ->map(fn (Sale $sale) => $this->serializarVendaResumo($sale))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'per_page' => $paginado->perPage(),
                'total' => $paginado->total(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function serializarVendaResumo(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'referencia' => $sale->referencia,
            'cliente' => $sale->cliente,
            'caixa' => $sale->caixa,
            'operador' => $sale->operador,
            'metodoPagamento' => $sale->metodo_pagamento,
            'total' => (float) $sale->total,
            'estado' => $sale->estado,
            'data' => $sale->data?->toIso8601String(),
            'createdAt' => $sale->created_at?->toIso8601String(),
        ];
    }
}
