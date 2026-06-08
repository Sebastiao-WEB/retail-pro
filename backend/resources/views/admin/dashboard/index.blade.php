@php
    $dashboard_index_blade_routes = [
'index' => route('dashboard')
    ];
@endphp

<x-layouts.desktop :title="__('pages.titles.dashboard')" admin-page="dashboard">
<div class="space-y-6" data-routes='@json($dashboard_index_blade_routes)'>
    <form method="GET" action="{{ route('dashboard') }}" data-auto-submit class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.register') }}</label>
                <select name="registerFilter" class="rp-input">
                    <option value="">{{ __('app.all_registers') }}</option>
                    @foreach ($registers as $register)
                        <option value="{{ $register->id }}" @selected($registerFilter === $register->id)>{{ $register->code }} — {{ $register->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.period') }}</label>
                <select name="period" class="rp-input">
                    <option value="today" @selected($period === 'today')>{{ __('app.periods.today') }}</option>
                    <option value="7d" @selected($period === '7d')>{{ __('app.periods.7d') }}</option>
                    <option value="30d" @selected($period === '30d')>{{ __('app.periods.30d') }}</option>
                    <option value="month" @selected($period === 'month')>{{ __('app.periods.month') }}</option>
                </select>
            </div>
        </div>
    </form>

    <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <article class="rounded-xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-emerald-100/70 p-4">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-700">
                    <i data-lucide="badge-dollar-sign" class="h-6 w-6"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-emerald-800/80">{{ $metricas['periodoRotulo'] }}</p>
                    <p class="mt-1 text-xl font-bold text-emerald-900">{{ number_format($metricas['vendasPeriodo'], 2, ',', '.') }} {{ __('app.currency') }}</p>
                </div>
            </div>
        </article>
        <article class="rounded-xl border border-blue-200 bg-gradient-to-br from-blue-50 to-blue-100/70 p-4">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-500/15 text-blue-700">
                    <i data-lucide="box" class="h-6 w-6"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-800/80">{{ __('pages.dashboard.active_products') }}</p>
                    <p class="mt-1 text-xl font-bold text-blue-900">{{ $metricas['totalProdutos'] }}</p>
                </div>
            </div>
        </article>
        <article class="rounded-xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-indigo-100/70 p-4">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-500/15 text-indigo-700">
                    <i data-lucide="users" class="h-6 w-6"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-indigo-800/80">{{ __('pages.dashboard.active_customers') }}</p>
                    <p class="mt-1 text-xl font-bold text-indigo-900">{{ $metricas['totalClientes'] }}</p>
                </div>
            </div>
        </article>
        <article class="rounded-xl border border-cyan-200 bg-gradient-to-br from-cyan-50 to-cyan-100/70 p-4">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-cyan-500/15 text-cyan-700">
                    <i data-lucide="rotate-cw" class="h-6 w-6"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-cyan-800/80">{{ __('pages.dashboard.month_reloads') }}</p>
                    <p class="mt-1 text-xl font-bold text-cyan-900">{{ $metricas['recargasMes'] }}</p>
                </div>
            </div>
        </article>
        <article class="rounded-xl border border-amber-200 bg-gradient-to-br from-amber-50 to-amber-100/70 p-4">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-500/20 text-amber-700">
                    <i data-lucide="undo-2" class="h-6 w-6"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-amber-800/80">{{ __('pages.dashboard.pending_reversals') }}</p>
                    <p class="mt-1 text-xl font-bold text-amber-900">{{ $metricas['reversoesPendentes'] }}</p>
                </div>
            </div>
        </article>
        <article class="rounded-xl border border-violet-200 bg-gradient-to-br from-violet-50 to-violet-100/70 p-4">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-violet-500/15 text-violet-700">
                    <i data-lucide="wallet" class="h-6 w-6"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-violet-800/80">{{ __('pages.dashboard.active_registers') }}</p>
                    <p class="mt-1 text-xl font-bold text-violet-900">{{ $metricas['caixasAtivos'] }}</p>
                </div>
            </div>
        </article>
    </section>

    <section class="grid grid-cols-1 gap-3 md:grid-cols-3">
        @can('sales.view')
            <a href="{{ route('sales.index') }}" class="rounded-lg border border-slate-200 bg-white p-4 transition hover:border-[var(--gold)]">
                <i data-lucide="shopping-cart" class="mb-2 h-4 w-4 text-slate-500"></i>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.dashboard.shortcuts.operation') }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">{{ __('pages.dashboard.shortcuts.view_sales') }}</p>
                <p class="text-xs text-slate-500">{{ __('pages.dashboard.shortcuts.view_sales_desc') }}</p>
            </a>
        @endcan
        @can('registers.view')
            <a href="{{ route('registers.index') }}" class="rounded-lg border border-slate-200 bg-white p-4 transition hover:border-[var(--gold)]">
                <i data-lucide="wallet" class="mb-2 h-4 w-4 text-slate-500"></i>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.dashboard.shortcuts.catalog') }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">{{ __('pages.dashboard.shortcuts.manage_registers') }}</p>
                <p class="text-xs text-slate-500">{{ __('pages.dashboard.shortcuts.manage_registers_desc') }}</p>
            </a>
        @endcan
        @can('stock_locations.view')
            <a href="{{ route('stock-locations.index') }}" class="rounded-lg border border-slate-200 bg-white p-4 transition hover:border-[var(--gold)]">
                <i data-lucide="warehouse" class="mb-2 h-4 w-4 text-slate-500"></i>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.dashboard.shortcuts.catalog') }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">{{ __('pages.dashboard.shortcuts.stock_locations') }}</p>
                <p class="text-xs text-slate-500">{{ __('pages.dashboard.shortcuts.stock_locations_desc') }}</p>
            </a>
        @endcan
        @can('stock.reload')
            <a href="{{ route('stock.reload') }}" class="rounded-lg border border-slate-200 bg-white p-4 transition hover:border-[var(--gold)]">
                <i data-lucide="rotate-cw" class="mb-2 h-4 w-4 text-slate-500"></i>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.dashboard.shortcuts.operation') }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">{{ __('pages.dashboard.shortcuts.reload_stock') }}</p>
                <p class="text-xs text-slate-500">{{ __('pages.dashboard.shortcuts.reload_stock_desc') }}</p>
            </a>
        @endcan
        @can('products.view')
            <a href="{{ route('products.index') }}" class="rounded-lg border border-slate-200 bg-white p-4 transition hover:border-[var(--gold)]">
                <i data-lucide="box" class="mb-2 h-4 w-4 text-slate-500"></i>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.dashboard.shortcuts.catalog') }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">{{ __('pages.dashboard.shortcuts.manage_products') }}</p>
                <p class="text-xs text-slate-500">{{ __('pages.dashboard.shortcuts.manage_products_desc') }}</p>
            </a>
        @endcan
        @can('stock.movements.view')
            <a href="{{ route('stock.movements') }}" class="rounded-lg border border-slate-200 bg-white p-4 transition hover:border-[var(--gold)]">
                <i data-lucide="arrow-right-left" class="mb-2 h-4 w-4 text-slate-500"></i>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.dashboard.shortcuts.stock') }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">{{ __('pages.dashboard.shortcuts.stock_movements') }}</p>
                <p class="text-xs text-slate-500">{{ __('pages.dashboard.shortcuts.stock_movements_desc') }}</p>
            </a>
        @endcan
    </section>

    <section class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <article class="rounded-lg border border-slate-200 bg-white p-4 xl:col-span-2">
            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $periodoGraficoRotulo }}</p>
            <div class="h-72">
                <canvas id="chartVendas7Dias" class="h-full w-full"></canvas>
            </div>
        </article>
        <article class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.dashboard.payment_methods') }}</p>
            <div class="h-72">
                <canvas id="chartPagamentos" class="h-full w-full"></canvas>
            </div>
        </article>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.dashboard.latest_sales') }}</p>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-2">{{ __('app.fields.reference') }}</th>
                        <th class="px-3 py-2">{{ __('app.fields.client') }}</th>
                        <th class="px-3 py-2">{{ __('app.fields.payment') }}</th>
                        <th class="px-3 py-2">{{ __('app.fields.total') }}</th>
                        <th class="px-3 py-2">{{ __('app.fields.date') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ultimasVendas as $venda)
                        <tr class="border-b border-slate-100">
                            <td class="px-3 py-2 font-medium text-slate-700">{{ $venda->referencia }}</td>
                            <td class="px-3 py-2">{{ $venda->cliente }}</td>
                            <td class="px-3 py-2">{{ $venda->metodo_pagamento }}</td>
                            <td class="px-3 py-2">{{ number_format((float) $venda->total, 2, ',', '.') }} {{ __('app.currency') }}</td>
                            <td class="px-3 py-2">{{ optional($venda->data)->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-6 text-center text-sm text-slate-500">{{ __('pages.dashboard.no_sales') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

@push('scripts')
<script>
    function renderDashboardCharts() {
        if (typeof window.Chart === 'undefined') return;

        const labelsVendas = @js($labelsVendas);
        const dadosVendas = @js($dadosVendas);
        const labelsPagamentos = @js($labelsPagamentos);
        const dadosPagamentos = @js($dadosPagamentos);
        const chartSalesLabel = @js($chartSalesLabel);

        if (window.retailChartVendas7Dias) {
            window.retailChartVendas7Dias.destroy();
            window.retailChartVendas7Dias = null;
        }

        if (window.retailChartPagamentos) {
            window.retailChartPagamentos.destroy();
            window.retailChartPagamentos = null;
        }

        const canvasVendas = document.getElementById('chartVendas7Dias');
        if (canvasVendas) {
            window.retailChartVendas7Dias = new window.Chart(canvasVendas, {
                type: 'line',
                data: {
                    labels: labelsVendas,
                    datasets: [{
                        label: chartSalesLabel,
                        data: dadosVendas,
                        borderColor: '#d8b65a',
                        backgroundColor: 'rgba(216, 182, 90, 0.18)',
                        fill: true,
                        tension: 0.28,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                },
            });
        }

        const canvasPagamentos = document.getElementById('chartPagamentos');
        if (canvasPagamentos) {
            window.retailChartPagamentos = new window.Chart(canvasPagamentos, {
                type: 'doughnut',
                data: {
                    labels: labelsPagamentos,
                    datasets: [{
                        data: dadosPagamentos,
                        backgroundColor: ['#0f172a', '#1e293b', '#334155', '#d8b65a', '#475569'],
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                },
            });
        }
    }

    window.addEventListener('load', renderDashboardCharts);
</script>
@endpush
</x-layouts.desktop>
