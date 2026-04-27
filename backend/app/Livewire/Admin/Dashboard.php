<?php

namespace App\Livewire\Admin;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleReversalRequest;
use App\Models\StockLocation;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $totalVendasHoje = (float) Sale::query()
            ->whereDate('data', now()->toDateString())
            ->sum('total');

        $vendasUltimos7Dias = Sale::query()
            ->selectRaw('DATE(data) as dia, COALESCE(SUM(total), 0) as total')
            ->where('data', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('dia')
            ->orderBy('dia')
            ->get();

        $metodosPagamento = Sale::query()
            ->selectRaw('metodo_pagamento, COUNT(*) as total')
            ->groupBy('metodo_pagamento')
            ->orderByDesc('total')
            ->get();

        $labelsVendas = $vendasUltimos7Dias
            ->map(fn ($row) => \Carbon\Carbon::parse($row->dia)->format('d/m'))
            ->values();

        $dadosVendas = $vendasUltimos7Dias
            ->map(fn ($row) => (float) $row->total)
            ->values();

        $labelsPagamentos = $metodosPagamento->pluck('metodo_pagamento')->map(fn ($item) => $item ?: 'N/A')->values();
        $dadosPagamentos = $metodosPagamento->pluck('total')->map(fn ($item) => (int) $item)->values();

        $metricas = [
            'vendasHoje' => $totalVendasHoje,
            'totalProdutos' => Product::query()->where('is_active', true)->count(),
            'totalClientes' => Customer::query()->where('is_active', true)->count(),
            'comprasMes' => (float) Purchase::query()
                ->where('data', '>=', now()->startOfMonth())
                ->sum('total'),
            'reversoesPendentes' => SaleReversalRequest::query()->where('status', 'PENDING')->count(),
            'caixasAtivos' => Register::query()->where('is_active', true)->count(),
            'locaisAtivos' => StockLocation::query()->where('is_active', true)->count(),
        ];

        $ultimasVendas = Sale::query()
            ->latest('data')
            ->limit(8)
            ->get(['referencia', 'cliente', 'metodo_pagamento', 'total', 'estado', 'data']);

        return view('livewire.admin.dashboard')
            ->layout('components.layouts.desktop', [
                'title' => 'Dashboard | RetailPro',
            ])
            ->with([
                'metricas' => $metricas,
                'ultimasVendas' => $ultimasVendas,
                'labelsVendas' => $labelsVendas,
                'dadosVendas' => $dadosVendas,
                'labelsPagamentos' => $labelsPagamentos,
                'dadosPagamentos' => $dadosPagamentos,
            ]);
    }
}
