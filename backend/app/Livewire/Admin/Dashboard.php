<?php

namespace App\Livewire\Admin;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleReversalRequest;
use App\Models\StockMovement;
use Livewire\Component;

class Dashboard extends Component
{
    public string $registerFilter = '';

    public string $period = '7d';

    public function updatedRegisterFilter(): void
    {
        // Re-render charts when filter changes.
    }

    public function updatedPeriod(): void
    {
        // Re-render charts when period changes.
    }

    private function periodRange(): array
    {
        $fim = now()->endOfDay();

        return match ($this->period) {
            'today' => [now()->startOfDay(), $fim],
            '30d' => [now()->subDays(29)->startOfDay(), $fim],
            'month' => [now()->startOfMonth(), $fim],
            default => [now()->subDays(6)->startOfDay(), $fim],
        };
    }

    private function salesBaseQuery()
    {
        [$inicio] = $this->periodRange();

        return Sale::query()
            ->when($this->registerFilter !== '', fn ($q) => $q->where('register_id', $this->registerFilter))
            ->where('data', '>=', $inicio);
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('dashboard.view'), 403);

        [$inicio, $fim] = $this->periodRange();

        $totalVendasPeriodo = (float) $this->salesBaseQuery()
            ->where('data', '<=', $fim)
            ->sum('total');

        $vendasPorDia = Sale::query()
            ->selectRaw('DATE(data) as dia, COALESCE(SUM(total), 0) as total')
            ->when($this->registerFilter !== '', fn ($q) => $q->where('register_id', $this->registerFilter))
            ->whereBetween('data', [$inicio, $fim])
            ->groupBy('dia')
            ->orderBy('dia')
            ->get()
            ->keyBy('dia');

        $labelsVendas = collect();
        $dadosVendas = collect();
        $cursor = $inicio->copy()->startOfDay();
        $ultimoDia = $fim->copy()->startOfDay();

        while ($cursor->lte($ultimoDia)) {
            $chave = $cursor->toDateString();
            $labelsVendas->push($cursor->format('d/m'));
            $dadosVendas->push((float) ($vendasPorDia[$chave]->total ?? 0));
            $cursor->addDay();
        }

        $metodosPagamento = Sale::query()
            ->selectRaw('metodo_pagamento, COUNT(*) as total')
            ->when($this->registerFilter !== '', fn ($q) => $q->where('register_id', $this->registerFilter))
            ->whereBetween('data', [$inicio, $fim])
            ->groupBy('metodo_pagamento')
            ->orderByDesc('total')
            ->get();

        $labelsPagamentos = $metodosPagamento->pluck('metodo_pagamento')->map(fn ($item) => $item ?: 'N/A')->values();
        $dadosPagamentos = $metodosPagamento->pluck('total')->map(fn ($item) => (int) $item)->values();

        $periodoRotulo = match ($this->period) {
            'today' => __('app.periods.sales_today'),
            '30d' => __('app.periods.sales_30d'),
            'month' => __('app.periods.sales_month'),
            default => __('app.periods.sales_7d'),
        };

        $metricas = [
            'vendasPeriodo' => $totalVendasPeriodo,
            'periodoRotulo' => $periodoRotulo,
            'totalProdutos' => Product::query()->where('is_active', true)->count(),
            'totalClientes' => Customer::query()->where('is_active', true)->count(),
            'recargasMes' => (int) StockMovement::query()
                ->stockReloads()
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
            'reversoesPendentes' => SaleReversalRequest::query()->where('status', 'PENDING')->count(),
            'caixasAtivos' => Register::query()->where('is_active', true)->count(),
        ];

        $ultimasVendas = Sale::query()
            ->when($this->registerFilter !== '', fn ($q) => $q->where('register_id', $this->registerFilter))
            ->whereBetween('data', [$inicio, $fim])
            ->latest('data')
            ->limit(8)
            ->get(['referencia', 'cliente', 'caixa', 'metodo_pagamento', 'total', 'estado', 'data']);

        $registers = Register::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);

        return view('livewire.admin.dashboard')
            ->layout('components.layouts.desktop', [
                'title' => __('pages.titles.dashboard'),
            ])
            ->with([
                'metricas' => $metricas,
                'ultimasVendas' => $ultimasVendas,
                'labelsVendas' => $labelsVendas,
                'dadosVendas' => $dadosVendas,
                'labelsPagamentos' => $labelsPagamentos,
                'dadosPagamentos' => $dadosPagamentos,
                'registers' => $registers,
                'periodoGraficoRotulo' => match ($this->period) {
                    'today' => __('app.periods.chart_today'),
                    '30d' => __('app.periods.chart_30d'),
                    'month' => __('app.periods.chart_month'),
                    default => __('app.periods.chart_7d'),
                },
                'chartSalesLabel' => __('pages.dashboard.chart_sales'),
            ]);
    }
}
