<x-layouts.desktop :title="__('pages.titles.sales_detail')" admin-page="sales-detail">
<div class="mx-auto max-w-5xl space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.sales.detail_title', ['reference' => $sale->referencia]) }}</p>
            <p class="text-sm text-slate-500">{{ optional($sale->data)->format('d/m/Y H:i') }} · {{ \App\Support\Translations::saleStatus($sale->estado) }}</p>
        </div>
        <a href="{{ $backUrl }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
            <i data-lucide="arrow-left" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.back') }}
        </a>
    </div>

    <div class="grid grid-cols-2 gap-3 rounded-lg border border-slate-200 bg-white p-5 text-sm md:grid-cols-4">
        <div>
            <p class="text-xs text-slate-500">{{ __('pages.sales.detail_client') }}</p>
            <p class="font-semibold">{{ $sale->cliente }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-500">{{ __('pages.sales.detail_operator') }}</p>
            <p>{{ $sale->operador ?? $sale->user?->name ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-500">{{ __('pages.sales.detail_register') }}</p>
            <p>{{ $sale->caixa ?? $sale->register?->name ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-500">{{ __('pages.sales.detail_payment') }}</p>
            <p>{{ \App\Support\Translations::paymentMethod($sale->metodo_pagamento) }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-500">{{ __('pages.sales.detail_status') }}</p>
            <p class="{{ strcasecmp($sale->estado, 'Revertida') === 0 ? 'text-red-600' : 'text-slate-700' }}">{{ \App\Support\Translations::saleStatus($sale->estado) }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-500">{{ __('pages.sales.detail_date') }}</p>
            <p>{{ optional($sale->data)->format('d/m/Y H:i') ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-500">{{ __('pages.sales.detail_total_iva') }}</p>
            <p>{{ number_format($totalIva, 2, ',', '.') }} MT</p>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-xs">
            <thead class="bg-slate-900 text-left uppercase tracking-wide text-white">
                <tr>
                    <th class="px-3 py-2">{{ __('app.fields.product') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('app.fields.quantity') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('app.fields.price') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('app.fields.iva') }} %</th>
                    <th class="px-3 py-2 text-right">{{ __('app.fields.iva_line_total') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('pages.common.subtotal') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sale->itens as $item)
                    @php
                        $tax = $item->resolvedTax();
                    @endphp
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2">{{ $item->nome }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $item->quantidade, 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $item->preco_venda, 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $tax['ivaPercentual'], 2, ',', '.') }}%</td>
                        <td class="px-3 py-2 text-right">{{ number_format($item->ivaTotalLinha(), 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-medium">{{ number_format((float) $item->subtotal, 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-slate-500">{{ __('pages.common.no_items') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap justify-end gap-4 rounded-lg border border-slate-200 bg-white p-5 text-sm">
        <span>{{ __('pages.sales.detail_subtotal') }}: <strong>{{ number_format((float) $sale->subtotal, 2, ',', '.') }} MT</strong></span>
        <span>{{ __('pages.sales.detail_discount') }}: <strong>{{ number_format((float) $sale->desconto_aplicado, 2, ',', '.') }} MT</strong></span>
        <span class="text-base font-semibold">{{ __('pages.sales.detail_total') }}: {{ number_format((float) $sale->total, 2, ',', '.') }} MT</span>
        @if ($isCash)
            <span>{{ __('pages.sales.detail_amount_paid') }}: {{ number_format((float) $sale->valor_pago, 2, ',', '.') }} MT</span>
            <span>{{ __('pages.sales.detail_change') }}: {{ number_format((float) $sale->troco, 2, ',', '.') }} MT</span>
        @endif
    </div>
</div>
</x-layouts.desktop>
