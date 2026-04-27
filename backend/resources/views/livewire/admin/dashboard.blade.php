<div class="space-y-6">
    <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-7">
        <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <i data-lucide="badge-dollar-sign" class="mb-2 h-4 w-4 text-slate-500"></i>
            <p class="text-xs uppercase tracking-widest text-slate-500">Vendas hoje</p>
            <p class="mt-2 text-lg font-semibold">{{ number_format($metricas['vendasHoje'], 2, ',', '.') }} MZN</p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <i data-lucide="box" class="mb-2 h-4 w-4 text-slate-500"></i>
            <p class="text-xs uppercase tracking-widest text-slate-500">Produtos ativos</p>
            <p class="mt-2 text-lg font-semibold">{{ $metricas['totalProdutos'] }}</p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <i data-lucide="users" class="mb-2 h-4 w-4 text-slate-500"></i>
            <p class="text-xs uppercase tracking-widest text-slate-500">Clientes ativos</p>
            <p class="mt-2 text-lg font-semibold">{{ $metricas['totalClientes'] }}</p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <i data-lucide="package-plus" class="mb-2 h-4 w-4 text-slate-500"></i>
            <p class="text-xs uppercase tracking-widest text-slate-500">Compras do mês</p>
            <p class="mt-2 text-lg font-semibold">{{ number_format($metricas['comprasMes'], 2, ',', '.') }} MZN</p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <i data-lucide="undo-2" class="mb-2 h-4 w-4 text-slate-500"></i>
            <p class="text-xs uppercase tracking-widest text-slate-500">Reversões pendentes</p>
            <p class="mt-2 text-lg font-semibold">{{ $metricas['reversoesPendentes'] }}</p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <i data-lucide="wallet" class="mb-2 h-4 w-4 text-slate-500"></i>
            <p class="text-xs uppercase tracking-widest text-slate-500">Caixas ativos</p>
            <p class="mt-2 text-lg font-semibold">{{ $metricas['caixasAtivos'] }}</p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <i data-lucide="warehouse" class="mb-2 h-4 w-4 text-slate-500"></i>
            <p class="text-xs uppercase tracking-widest text-slate-500">Locais de stock ativos</p>
            <p class="mt-2 text-lg font-semibold">{{ $metricas['locaisAtivos'] }}</p>
        </article>
    </section>

    <section class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
        @can('registers.view')
            <a href="{{ route('registers.index') }}" class="rounded-lg border border-slate-200 bg-white p-4 transition hover:border-[var(--gold)]">
                <i data-lucide="wallet" class="mb-2 h-4 w-4 text-slate-500"></i>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Cadastro</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">Gerir Caixas</p>
                <p class="text-xs text-slate-500">Criar, editar e ativar/desativar caixas.</p>
            </a>
        @endcan
        @can('stock_locations.view')
            <a href="{{ route('stock-locations.index') }}" class="rounded-lg border border-slate-200 bg-white p-4 transition hover:border-[var(--gold)]">
                <i data-lucide="warehouse" class="mb-2 h-4 w-4 text-slate-500"></i>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Cadastro</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">Armazéns e Localizações</p>
                <p class="text-xs text-slate-500">Mapear local de venda, armazém e áreas técnicas.</p>
            </a>
        @endcan
        @can('stock.reload')
            <a href="{{ route('stock.reload') }}" class="rounded-lg border border-slate-200 bg-white p-4 transition hover:border-[var(--gold)]">
                <i data-lucide="rotate-cw" class="mb-2 h-4 w-4 text-slate-500"></i>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Operação</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">Recarregar Stock</p>
                <p class="text-xs text-slate-500">Entrada rápida de stock com rastreio em compras.</p>
            </a>
        @endcan
        @can('products.view')
            <a href="{{ route('products.index') }}" class="rounded-lg border border-slate-200 bg-white p-4 transition hover:border-[var(--gold)]">
                <i data-lucide="box" class="mb-2 h-4 w-4 text-slate-500"></i>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Catálogo</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">Gestão de Produtos</p>
                <p class="text-xs text-slate-500">Atualizar preços, stock e estado de venda.</p>
            </a>
        @endcan
        @can('purchases.view')
            <a href="{{ route('purchases.index') }}" class="rounded-lg border border-slate-200 bg-white p-4 transition hover:border-[var(--gold)]">
                <i data-lucide="package-plus" class="mb-2 h-4 w-4 text-slate-500"></i>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Operação</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">Registrar Compras</p>
                <p class="text-xs text-slate-500">Entrada formal de mercadoria com totais e itens.</p>
            </a>
        @endcan
    </section>

    <section class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <article class="rounded-lg border border-slate-200 bg-white p-4 xl:col-span-2">
            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Vendas dos últimos 7 dias</p>
            <div class="h-72">
                <canvas id="chartVendas7Dias" class="h-full w-full"></canvas>
            </div>
        </article>
        <article class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Métodos de pagamento</p>
            <div class="h-72">
                <canvas id="chartPagamentos" class="h-full w-full"></canvas>
            </div>
        </article>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Últimas vendas</p>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-2">Referência</th>
                        <th class="px-3 py-2">Cliente</th>
                        <th class="px-3 py-2">Pagamento</th>
                        <th class="px-3 py-2">Total</th>
                        <th class="px-3 py-2">Data</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ultimasVendas as $venda)
                        <tr class="border-b border-slate-100">
                            <td class="px-3 py-2 font-medium text-slate-700">{{ $venda->referencia }}</td>
                            <td class="px-3 py-2">{{ $venda->cliente }}</td>
                            <td class="px-3 py-2">{{ $venda->metodo_pagamento }}</td>
                            <td class="px-3 py-2">{{ number_format((float) $venda->total, 2, ',', '.') }} MZN</td>
                            <td class="px-3 py-2">{{ optional($venda->data)->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-6 text-center text-sm text-slate-500">Sem vendas registadas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
    function renderDashboardCharts() {
        if (typeof window.Chart === 'undefined') return;

        const labelsVendas = @js($labelsVendas);
        const dadosVendas = @js($dadosVendas);
        const labelsPagamentos = @js($labelsPagamentos);
        const dadosPagamentos = @js($dadosPagamentos);

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
                        label: 'Vendas (MZN)',
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
    window.addEventListener('livewire:navigated', renderDashboardCharts);
    window.addEventListener('livewire:initialized', () => {
        if (window.Livewire?.hook) {
            window.Livewire.hook('morph.updated', () => {
                renderDashboardCharts();
            });
        }
    });
</script>
