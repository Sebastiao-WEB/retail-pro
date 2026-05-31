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

        $ultimasVendas = Sale::query()
            ->when($registerId, fn ($q) => $q->where('register_id', $registerId))
            ->whereBetween('data', [$inicio, $fim])
            ->latest('data')
            ->limit(8)
            ->get(['id', 'referencia', 'cliente', 'caixa', 'metodo_pagamento', 'total', 'estado', 'data'])
            ->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'referencia' => $sale->referencia,
                'cliente' => $sale->cliente,
                'caixa' => $sale->caixa,
                'metodoPagamento' => $sale->metodo_pagamento,
                'total' => (float) $sale->total,
                'estado' => $sale->estado,
                'data' => $sale->data?->toIso8601String(),
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
            'ultimasVendas' => $ultimasVendas,
        ];
    }
}
