@php
    $sales_index_blade_routes = [
'index' => route('sales.index'),
        'show' => route('sales.show', ['sale' => '__ID__']),
        'export' => route('sales.export'),
    ];
@endphp

<x-layouts.desktop :title="__('pages.titles.sales')" admin-page="sales">
<div
    class="space-y-4"
    data-routes='@json($sales_index_blade_routes)'
>
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.sales.title') }}</p>
            <p class="text-sm text-slate-500">{{ __('pages.sales.subtitle') }}</p>
            <p class="mt-1 text-xs font-semibold text-emerald-700">
                {{ __('pages.sales.filtered_total') }}: {{ number_format($totalFiltrado, 2, ',', '.') }} MT
            </p>
        </div>
        @can('sales.export')
            <button type="button" data-action="export-csv" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                <i data-lucide="download" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
                {{ __('pages.sales.export_csv') }}
            </button>
        @endcan
    </div>

    <form method="GET" action="{{ route('sales.index') }}" data-auto-submit data-debounce="300" class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            <div class="md:col-span-2 xl:col-span-3">
                <input name="search" type="text" value="{{ $search }}" class="rp-input" placeholder="{{ __('pages.sales.search_placeholder') }}">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.register') }}</label>
                <select name="registerFilter" class="rp-input">
                    <option value="">{{ __('app.all') }}</option>
                    @foreach ($registers as $register)
                        <option value="{{ $register->id }}" @selected($registerFilter === $register->id)>{{ $register->code }} — {{ $register->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.status') }}</label>
                <select name="estadoFilter" class="rp-input">
                    <option value="">{{ __('app.all') }}</option>
                    <option value="Concluida" @selected($estadoFilter === 'Concluida')>{{ \App\Support\Translations::saleStatus('Concluida') }}</option>
                    <option value="Revertida" @selected($estadoFilter === 'Revertida')>{{ \App\Support\Translations::saleStatus('Revertida') }}</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.payment') }}</label>
                <select name="pagamentoFilter" class="rp-input">
                    <option value="">{{ __('app.all') }}</option>
                    <option value="Dinheiro" @selected($pagamentoFilter === 'Dinheiro')>{{ \App\Support\Translations::paymentMethod('Dinheiro') }}</option>
                    <option value="Transferência" @selected($pagamentoFilter === 'Transferência')>{{ \App\Support\Translations::paymentMethod('Transferência') }}</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.date_from') }}</label>
                <input name="dateFrom" type="date" value="{{ $dateFrom }}" class="rp-input">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.date_to') }}</label>
                <input name="dateTo" type="date" value="{{ $dateTo }}" class="rp-input">
            </div>
            <div class="flex items-end">
                <a href="{{ route('sales.index') }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                    {{ __('app.clear_filters') }}
                </a>
            </div>
        </div>
    </form>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">{{ __('app.fields.reference') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.client') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.register') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.payment') }}</th>
                    <th class="px-3 py-2">{{ __('app.status') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.total') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.date') }}</th>
                    <th class="px-3 py-2">{{ __('app.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($vendas as $venda)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 font-medium">{{ $venda->referencia }}</td>
                        <td class="px-3 py-2">{{ $venda->cliente }}</td>
                        <td class="px-3 py-2">{{ $venda->caixa ?? $venda->register?->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ \App\Support\Translations::paymentMethod($venda->metodo_pagamento) }}</td>
                        <td class="px-3 py-2">
                            <span class="{{ strcasecmp($venda->estado, 'Revertida') === 0 ? 'text-red-600' : 'text-slate-700' }}">{{ \App\Support\Translations::saleStatus($venda->estado) }}</span>
                        </td>
                        <td class="px-3 py-2">{{ number_format((float) $venda->total, 2, ',', '.') }} MT</td>
                        <td class="px-3 py-2">{{ optional($venda->data)->format('d/m/Y H:i') }}</td>
                        <td class="px-3 py-2">
                            <button type="button" data-action="open-detail" data-id="{{ $venda->id }}" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">{{ __('app.details') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-6 text-center text-slate-500">{{ __('pages.sales.no_sales') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $vendas->links() }}</div>

    <div id="sale-detail-modal" class="rp-admin-modal hidden fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4" aria-hidden="true"></div>
</div>
</x-layouts.desktop>
