@php
    $editable = $canManage && ! $balance->isFinalized();
    $locationGroups = $balance->locationLines->groupBy('location_id');
    $balance_sheets_show_blade_routes = [
        'index' => route('balance-sheets.index'),
        'show' => route('balance-sheets.show', ['balanceSheet' => $balance->id]),
        'update' => route('balance-sheets.update', ['balanceSheet' => $balance->id]),
        'recalculate' => route('balance-sheets.recalculate', ['balanceSheet' => $balance->id]),
        'finalize' => route('balance-sheets.finalize', ['balanceSheet' => $balance->id]),
    ];
@endphp

<x-layouts.desktop :title="__('pages.titles.balance_sheets_show')" admin-page="balance-sheets-show">
<div
    class="mx-auto max-w-6xl space-y-4"
    data-routes='@json($balance_sheets_show_blade_routes)'
    data-balance-id="{{ $balance->id }}"
>
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.balance_sheets.show_title') }}</p>
            <p class="text-base font-semibold text-slate-900">{{ $balance->referencia }} — {{ $balance->titulo }}</p>
            <p class="text-sm text-slate-500">
                {{ __('pages.common.period_label') }} {{ optional($balance->periodo_inicio)->format('d/m/Y') ?? '—' }}
                — {{ optional($balance->periodo_fim)->format('d/m/Y') ?? '—' }}
                · {{ __('pages.common.closing_label') }} {{ $balance->data_referencia->format('d/m/Y') }}
                ·
                <span class="{{ $balance->status === 'FINALIZED' ? 'text-emerald-600' : 'text-amber-600' }}">
                    {{ \App\Support\Translations::balanceStatus($balance->status) }}
                </span>
            </p>
        </div>
        <a href="{{ $backUrl }}" data-rp-page-nav class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
            <i data-lucide="arrow-left" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.back') }}
        </a>
    </div>

    @if ($editable)
        <form id="balance-detail-form" class="grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-5 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.title_label') }}</label>
                <input name="titulo" type="text" value="{{ old('titulo', $balance->titulo) }}" class="rp-input">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.notes_optional') }}</label>
                <input name="notas" type="text" value="{{ old('notas', $balance->notas) }}" class="rp-input">
            </div>
        </form>
    @elseif ($balance->notas)
        <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pages.common.notes_optional') }}</p>
            <p class="mt-1">{{ $balance->notas }}</p>
        </div>
    @endif

    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <p class="text-[10px] uppercase text-slate-500">{{ __('pages.common.reloads') }}</p>
            <p class="text-sm font-semibold">{{ number_format((float) $balance->total_recargas_valor, 2, ',', '.') }} {{ __('app.currency') }}</p>
            <p class="text-[10px] text-slate-500">{{ number_format((float) $balance->total_recargas_qtd, 0, ',', '.') }} un.</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <p class="text-[10px] uppercase text-slate-500">{{ __('pages.common.sales_amount') }}</p>
            <p class="text-sm font-semibold">{{ number_format((float) $balance->total_vendas_valor, 2, ',', '.') }} {{ __('app.currency') }}</p>
            <p class="text-[10px] text-slate-500">{{ number_format((float) $balance->total_vendas_qtd, 0, ',', '.') }} un.</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <p class="text-[10px] uppercase text-slate-500">{{ __('pages.common.cost_sold') }}</p>
            <p class="text-sm font-semibold">{{ number_format((float) $balance->total_custo_vendas, 2, ',', '.') }} {{ __('app.currency') }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <p class="text-[10px] uppercase text-slate-500">{{ __('pages.common.period_profit') }}</p>
            <p class="text-sm font-semibold text-emerald-700">{{ number_format((float) $balance->total_lucro, 2, ',', '.') }} {{ __('app.currency') }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <p class="text-[10px] uppercase text-slate-500">{{ __('pages.common.stock_cost') }}</p>
            <p class="text-sm font-semibold">{{ number_format((float) $balance->total_stock_valor_compra, 2, ',', '.') }} {{ __('app.currency') }}</p>
            <p class="text-[10px] text-slate-500">{{ number_format((float) $balance->total_stock_qtd, 0, ',', '.') }} un.</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <p class="text-[10px] uppercase text-slate-500">{{ __('pages.common.stock_sale') }}</p>
            <p class="text-sm font-semibold">{{ number_format((float) $balance->total_stock_valor_venda, 2, ',', '.') }} {{ __('app.currency') }}</p>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-xs">
            <thead class="bg-slate-900 text-left uppercase tracking-wide text-white">
                <tr>
                    <th class="px-3 py-2">{{ __('app.fields.product') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.code') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('pages.common.reload_qty') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('pages.common.reload_value') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('pages.common.sold_qty') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('pages.common.sold_value') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('pages.common.cost_sold') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('pages.common.period_profit') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('pages.common.stock_qty') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('pages.common.stock_cost_short') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('pages.common.stock_sale_short') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($balance->lines as $linha)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 font-medium">{{ $linha->rubrika }}</td>
                        <td class="px-3 py-2 font-mono text-[11px] text-slate-600">{{ $linha->product?->codigo_barras ?? '—' }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $linha->qtd_recarregada, 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $linha->valor_recarga, 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $linha->qtd_vendida, 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $linha->valor_vendas, 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $linha->custo_vendas, 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-medium text-emerald-700">{{ number_format((float) $linha->lucro, 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $linha->qtd_stock, 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $linha->valor_stock_compra, 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $linha->valor_stock_venda, 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-3 py-6 text-center text-slate-500">{{ __('pages.balance_sheets.no_lines') }}</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($balance->lines->isNotEmpty())
                <tfoot class="bg-slate-50 font-semibold">
                    <tr class="border-t border-slate-200">
                        <td class="px-3 py-2">{{ __('pages.common.totals') }}</td>
                        <td class="px-3 py-2"></td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $balance->total_recargas_qtd, 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $balance->total_recargas_valor, 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $balance->total_vendas_qtd, 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $balance->total_vendas_valor, 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $balance->total_custo_vendas, 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right text-emerald-700">{{ number_format((float) $balance->total_lucro, 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $balance->total_stock_qtd, 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $balance->total_stock_valor_compra, 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $balance->total_stock_valor_venda, 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    @if ($locationGroups->isNotEmpty())
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="bg-slate-800 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white">
                {{ __('pages.common.stock_by_location_audit') }}
            </div>
            @foreach ($locationGroups as $linhasLocal)
                @php $cabecalho = $linhasLocal->first(); @endphp
                <div class="border-t border-slate-200">
                    <div class="flex flex-wrap items-center justify-between gap-2 bg-slate-50 px-3 py-2 text-xs">
                        <span class="font-semibold">{{ $cabecalho->local_codigo }} — {{ $cabecalho->local_nome }}</span>
                        <span class="text-slate-600">
                            {{ number_format((float) $linhasLocal->sum('quantity'), 0, ',', '.') }} un.
                            · {{ number_format((float) $linhasLocal->sum('valor_compra'), 2, ',', '.') }} {{ __('app.currency') }} ({{ __('pages.common.cost') }})
                        </span>
                    </div>
                    <table class="min-w-full text-xs">
                        <thead class="bg-slate-100 text-left uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2">{{ __('app.fields.product') }}</th>
                                <th class="px-3 py-2">{{ __('app.fields.code') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('pages.common.qty_short') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('pages.common.cost') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('pages.common.sale_value') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($linhasLocal as $linhaLocal)
                                <tr class="border-t border-slate-100">
                                    <td class="px-3 py-2">{{ $linhaLocal->produto_nome }}</td>
                                    <td class="px-3 py-2 font-mono text-[11px]">{{ $linhaLocal->codigo_barras ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format((float) $linhaLocal->quantity, 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format((float) $linhaLocal->valor_compra, 2, ',', '.') }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format((float) $linhaLocal->valor_venda, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    @endif

    <div class="flex flex-wrap justify-end gap-2 rounded-lg border border-slate-200 bg-white p-4">
        <a
            href="{{ route('balance-sheets.pdf', $balance) }}"
            data-rp-page-nav
            title="{{ __('pages.common.generate_pdf') }}"
            aria-label="{{ __('pages.common.generate_pdf') }}: {{ $balance->referencia }}"
            class="inline-flex h-8 items-center justify-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-3 text-xs font-semibold text-rose-700 hover:bg-rose-100"
        >
            <i data-lucide="file-text" class="h-3.5 w-3.5"></i><span>PDF</span>
        </a>
        @if ($editable)
            <button type="button" data-action="balance-recalculate" title="{{ __('pages.common.recalculate') }}" aria-label="{{ __('pages.common.recalculate') }}" class="inline-flex h-8 items-center justify-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-3 text-xs font-semibold text-amber-900 hover:bg-amber-100">
                <i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i><span>{{ __('pages.common.recalculate') }}</span>
            </button>
            <button type="submit" form="balance-detail-form" title="{{ __('app.save') }}" aria-label="{{ __('app.save') }}" class="inline-flex h-8 items-center justify-center gap-1 rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                <i data-lucide="save" class="h-3.5 w-3.5"></i><span>{{ __('app.save') }}</span>
            </button>
            <button type="button" data-action="balance-finalize" title="{{ __('pages.common.finalize') }}" aria-label="{{ __('pages.common.finalize') }}" class="inline-flex h-8 items-center justify-center gap-1 rounded-lg bg-[var(--gold)] px-3 text-xs font-semibold text-black hover:brightness-95">
                <i data-lucide="badge-check" class="h-3.5 w-3.5"></i><span>{{ __('pages.common.finalize') }}</span>
            </button>
        @endif
    </div>
</div>
</x-layouts.desktop>
