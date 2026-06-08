@php
    $balance_sheets_index_blade_routes = [
'index' => route('balance-sheets.index'),
        'show' => route('balance-sheets.show', ['balanceSheet' => '__ID__']),
        'store' => route('balance-sheets.store'),
        'update' => route('balance-sheets.update', ['balanceSheet' => '__ID__']),
        'recalculate' => route('balance-sheets.recalculate', ['balanceSheet' => '__ID__']),
        'finalize' => route('balance-sheets.finalize', ['balanceSheet' => '__ID__']),
    ];
@endphp

<x-layouts.desktop :title="__('pages.titles.balance_sheets')" admin-page="balance-sheets">
<div
    class="space-y-4"
    data-routes='@json($balance_sheets_index_blade_routes)'
    data-default-form='@json($defaultForm)'
>
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.balance_sheets.title') }}</p>
            <p class="text-sm text-slate-500">{{ __('pages.balance_sheets.subtitle') }}</p>
        </div>
        @can('balance_sheets.manage')
            <button type="button" data-action="open-create" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                <i data-lucide="plus" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
                {{ __('pages.balance_sheets.new') }}
            </button>
        @endcan
    </div>

    <form method="GET" action="{{ route('balance-sheets.index') }}" data-auto-submit data-debounce="300" class="rounded-lg border border-slate-200 bg-white p-4">
        <input name="search" type="text" value="{{ $search }}" class="rp-input" placeholder="{{ __('pages.balance_sheets.search_placeholder') }}">
    </form>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">{{ __('app.fields.reference') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.period') }}</th>
                    <th class="px-3 py-2">{{ __('pages.common.reloads') }}</th>
                    <th class="px-3 py-2">{{ __('pages.common.sales_amount') }}</th>
                    <th class="px-3 py-2">{{ __('pages.common.profit') }}</th>
                    <th class="px-3 py-2">{{ __('pages.common.stock_cost') }}</th>
                    <th class="px-3 py-2">{{ __('app.status') }}</th>
                    <th class="px-3 py-2">{{ __('app.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($balances as $balance)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2">
                            <p class="font-medium">{{ $balance->referencia }}</p>
                            <p class="text-xs text-slate-500">{{ $balance->titulo }}</p>
                        </td>
                        <td class="px-3 py-2 text-xs">
                            {{ optional($balance->periodo_inicio)->format('d/m/Y') ?? '—' }}
                            — {{ optional($balance->periodo_fim)->format('d/m/Y') ?? '—' }}
                        </td>
                        <td class="px-3 py-2">{{ number_format((float) $balance->total_recargas_valor, 2, ',', '.') }} MT</td>
                        <td class="px-3 py-2">{{ number_format((float) $balance->total_vendas_valor, 2, ',', '.') }} MT</td>
                        <td class="px-3 py-2 font-medium text-emerald-700">{{ number_format((float) $balance->total_lucro, 2, ',', '.') }} MT</td>
                        <td class="px-3 py-2">{{ number_format((float) $balance->total_stock_valor_compra, 2, ',', '.') }} MT</td>
                        <td class="px-3 py-2">
                            <span class="{{ $balance->status === 'FINALIZED' ? 'text-emerald-600' : 'text-amber-600' }}">
                                {{ \App\Support\Translations::balanceStatus($balance->status) }}
                            </span>
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex flex-wrap items-center gap-1">
                                <button
                                    type="button"
                                    data-action="open-edit"
                                    data-id="{{ $balance->id }}"
                                    title="{{ __('pages.common.view') }}"
                                    aria-label="{{ __('pages.common.view') }}: {{ $balance->referencia }}"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-emerald-200 text-emerald-700 hover:bg-emerald-50"
                                >
                                    <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                </button>
                                <a
                                    href="{{ route('balance-sheets.pdf', $balance) }}"
                                    data-rp-page-nav
                                    title="{{ __('pages.common.generate_pdf') }}"
                                    aria-label="{{ __('pages.common.generate_pdf') }}: {{ $balance->referencia }}"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-rose-200 text-rose-700 hover:bg-rose-50"
                                >
                                    <i data-lucide="file-text" class="h-3.5 w-3.5"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-6 text-center text-slate-500">{{ __('pages.balance_sheets.no_balances') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $balances->links() }}</div>

    @can('balance_sheets.manage')
        <div id="balance-create-modal" class="rp-admin-modal hidden fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4" aria-hidden="true">
            <div class="w-full max-w-lg rounded-xl bg-white shadow-xl">
                <div class="border-b border-slate-200 px-5 py-3">
                    <h3 class="text-base font-semibold">{{ __('pages.balance_sheets.create_title') }}</h3>
                    <p class="text-xs text-slate-500">{{ __('pages.balance_sheets.create_subtitle') }}</p>
                </div>
                <form id="balance-create-form" class="space-y-3 p-5">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.title_label') }}</label>
                        <input name="titulo" type="text" value="{{ $defaultForm['titulo'] }}" class="rp-input">
                        <p data-field-error="titulo" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.closing_date') }}</label>
                        <input name="data_referencia" type="date" value="{{ $defaultForm['data_referencia'] }}" class="rp-input">
                        <p data-field-error="data_referencia" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.period_start') }}</label>
                            <input name="periodo_inicio" type="date" value="{{ $defaultForm['periodo_inicio'] }}" class="rp-input">
                            <p data-field-error="periodo_inicio" class="mt-1 hidden text-xs text-red-600"></p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.period_end') }}</label>
                            <input name="periodo_fim" type="date" value="{{ $defaultForm['periodo_fim'] }}" class="rp-input">
                            <p data-field-error="periodo_fim" class="mt-1 hidden text-xs text-red-600"></p>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.notes_optional') }}</label>
                        <textarea name="notas" rows="2" class="rp-input">{{ $defaultForm['notas'] }}</textarea>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-200 pt-3">
                        <button type="button" data-modal-close="balance-create-modal" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">
                            <i data-lucide="x" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.cancel') }}
                        </button>
                        <button type="submit" data-action="create-balance" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black">
                            <i data-lucide="calculator" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('pages.common.calculate_balance') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    <div id="balance-detail-modal" class="rp-admin-modal hidden fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4" aria-hidden="true">
        <div class="max-h-[92vh] w-full max-w-6xl overflow-y-auto rounded-xl bg-white shadow-xl">
            <div id="balance-detail-content"></div>
            <form id="balance-detail-form" class="hidden"></form>
        </div>
    </div>
</div>
</x-layouts.desktop>
