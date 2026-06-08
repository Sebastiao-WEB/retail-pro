@php
    $itens = $stock['itens'] ?? [];
    $totalQtd = (float) ($stock['total_qtd'] ?? 0);
    $totalValorCompra = (float) ($stock['total_valor_compra'] ?? 0);
    $totalValorVenda = (float) ($stock['total_valor_venda'] ?? 0);
@endphp

<x-layouts.desktop :title="__('pages.titles.stock_locations_stock')" admin-page="stock-locations-stock">
<div class="mx-auto max-w-5xl space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                {{ __('pages.stock_locations.stock_title', ['location' => $location->code.' — '.$location->name]) }}
            </p>
            <p class="text-sm text-slate-500">
                {{ $location->type }}
                @if ($location->registers->isNotEmpty())
                    · {{ __('app.fields.registers') }}: {{ $location->registerCodesLabel() }}
                @endif
                ·
                <span class="{{ $location->is_active ? 'text-emerald-600' : 'text-red-600' }}">
                    {{ $location->is_active ? __('app.active') : __('app.inactive') }}
                </span>
            </p>
        </div>
        <a href="{{ $backUrl }}" data-rp-page-nav class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
            <i data-lucide="arrow-left" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.back') }}
        </a>
    </div>

    <div class="grid grid-cols-2 gap-3 rounded-lg border border-slate-200 bg-white p-5 text-sm md:grid-cols-4">
        <div>
            <p class="text-xs text-slate-500">{{ __('pages.common.products_count') }}</p>
            <p class="font-semibold">{{ count($itens) }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-500">{{ __('pages.common.total_qty') }}</p>
            <p class="font-semibold">{{ number_format($totalQtd, 2, ',', '.') }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-500">{{ __('pages.common.purchase_value') }}</p>
            <p class="font-semibold">{{ number_format($totalValorCompra, 2, ',', '.') }} {{ __('app.currency') }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-500">{{ __('pages.common.sale_value') }}</p>
            <p class="font-semibold">{{ number_format($totalValorVenda, 2, ',', '.') }} {{ __('app.currency') }}</p>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-xs">
            <thead class="bg-slate-900 text-left uppercase tracking-wide text-white">
                <tr>
                    <th class="px-3 py-2">{{ __('app.fields.product') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.code') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('pages.common.qty_short') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('pages.common.cost') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('pages.common.sale_value') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($itens as $item)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2">{{ $item['produto_nome'] ?? '—' }}</td>
                        <td class="px-3 py-2 font-mono text-[11px]">{{ $item['codigo_barras'] ?? '—' }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) ($item['quantity'] ?? 0), 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) ($item['valor_compra'] ?? 0), 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) ($item['valor_venda'] ?? 0), 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-6 text-center text-slate-500">{{ __('pages.common.no_stock_location') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
</x-layouts.desktop>
