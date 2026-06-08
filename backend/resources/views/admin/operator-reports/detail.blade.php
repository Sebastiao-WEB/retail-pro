<x-layouts.desktop :title="__('pages.titles.operator_reports_detail')" admin-page="operator-reports-detail">
<div class="mx-auto max-w-5xl space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.operator_reports.detail_title') }}</p>
            <p class="text-sm text-slate-500">
                {{ $operador['nome'] }} ·
                {{ __('pages.operator_reports.operator_sales_summary', [
                    'sales' => number_format($operador['total_vendas'], 2, ',', '.'),
                    'profit' => number_format($operador['total_lucro'], 2, ',', '.'),
                    'count' => __('pages.common.sales_count', ['count' => $operador['num_vendas']]),
                ]) }}
            </p>
        </div>
        <a href="{{ $backUrl }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
            <i data-lucide="arrow-left" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.back') }}
        </a>
    </div>

    <div class="space-y-4">
        @forelse ($operador['vendas'] as $venda)
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                <div class="flex flex-wrap items-center justify-between gap-2 bg-slate-50 px-4 py-2 text-xs">
                    <div>
                        <span class="font-semibold">{{ $venda['referencia'] }}</span>
                        <span class="text-slate-500">· {{ optional($venda['data'])->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="text-right">
                        <span class="font-semibold">{{ number_format($venda['total'], 2, ',', '.') }} MT</span>
                        <span class="text-emerald-700">· {{ __('pages.common.profit') }} {{ number_format($venda['lucro'], 2, ',', '.') }} MT</span>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2 border-b border-slate-100 px-4 py-2 text-[11px] text-slate-600 md:grid-cols-4">
                    <span><strong>{{ __('app.fields.client') }}:</strong> {{ $venda['cliente'] }}</span>
                    <span><strong>{{ __('app.fields.register') }}:</strong> {{ $venda['caixa'] ?? '—' }}</span>
                    <span><strong>{{ __('app.fields.payment') }}:</strong> {{ \App\Support\Translations::paymentMethod($venda['metodo_pagamento']) }}</span>
                    <span><strong>{{ __('pages.common.cost') }}:</strong> {{ number_format($venda['custo'], 2, ',', '.') }} MT</span>
                </div>
                <table class="min-w-full text-xs">
                    <thead class="bg-slate-900 text-left uppercase tracking-wide text-white">
                        <tr>
                            <th class="px-3 py-2">{{ __('app.fields.product') }}</th>
                            <th class="px-3 py-2">{{ __('pages.common.barcode_short') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('pages.common.qty_short') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('pages.common.sale_price_short') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('pages.common.cost_short') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('pages.common.profit') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($venda['itens'] as $item)
                            <tr class="border-t border-slate-100">
                                <td class="px-3 py-2">{{ $item['nome'] }}</td>
                                <td class="px-3 py-2 font-mono text-[11px]">{{ $item['codigo_barras'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($item['quantidade'], 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($item['subtotal'], 2, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($item['custo_total'], 2, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right text-emerald-700">{{ number_format($item['lucro'], 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <div class="rounded-lg border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-500">
                {{ __('pages.operator_reports.no_sales') }}
            </div>
        @endforelse
    </div>
</div>
</x-layouts.desktop>
