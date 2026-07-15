@php
    $operator_reports_index_blade_routes = [
'index' => route('operator-reports.index'),
        'pdf' => $pdfUrl,
    ];
@endphp

<x-layouts.desktop :title="__('pages.titles.operator_reports')" admin-page="operator-reports">
<div
    class="space-y-4"
    data-routes='@json($operator_reports_index_blade_routes)'
>
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.operator_reports.title') }}</p>
            <p class="text-sm text-slate-500">{{ __('pages.operator_reports.subtitle') }}</p>
        </div>
        <a href="{{ $pdfUrl }}" target="_blank" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
            <i data-lucide="file-down" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
            {{ __('pages.common.generate_pdf') }}
        </a>
    </div>

    <form method="GET" action="{{ route('operator-reports.index') }}" data-auto-submit data-operator-filters class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.period_start') }}</label>
                <input name="periodo_inicio" type="date" value="{{ $periodo_inicio }}" class="rp-input">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.period_end') }}</label>
                <input name="periodo_fim" type="date" value="{{ $periodo_fim }}" class="rp-input">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.register') }}</label>
                <select name="registerFilter" class="rp-input">
                    <option value="">{{ __('app.all_registers') }}</option>
                    @foreach ($registers as $register)
                        <option value="{{ $register->id }}" @selected($registerFilter === $register->id)>{{ $register->name }} ({{ $register->code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="button" data-action="apply-this-month" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold hover:bg-slate-50">{{ __('pages.common.this_month') }}</button>
                <button type="button" data-action="apply-previous-month" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold hover:bg-slate-50">{{ __('pages.common.previous_month') }}</button>
            </div>
        </div>
    </form>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-[10px] uppercase text-slate-500">{{ __('pages.operator_reports.total_sales') }}</p>
            <p class="text-lg font-semibold">{{ number_format($relatorio['totais']['vendas'], 2, ',', '.') }} MT</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-[10px] uppercase text-slate-500">{{ __('pages.common.cost_sold') }}</p>
            <p class="text-lg font-semibold">{{ number_format($relatorio['totais']['custo'], 2, ',', '.') }} MT</p>
        </div>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-[10px] uppercase text-emerald-700">{{ __('pages.common.profit') }}</p>
            <p class="text-lg font-semibold text-emerald-800">{{ number_format($relatorio['totais']['lucro'], 2, ',', '.') }} MT</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-[10px] uppercase text-slate-500">{{ __('pages.common.num_sales') }}</p>
            <p class="text-lg font-semibold">{{ number_format($relatorio['totais']['num_vendas'], 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">#</th>
                    <th class="px-3 py-2">{{ __('app.fields.operator') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('pages.common.sales_amount') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('pages.common.cost') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('pages.common.profit') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('pages.common.num_sales_short') }}</th>
                    <th class="px-3 py-2">{{ __('app.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($relatorio['operadores'] as $index => $operador)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 text-slate-500">{{ $index + 1 }}</td>
                        <td class="px-3 py-2 font-medium">{{ $operador['nome'] }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($operador['total_vendas'], 2, ',', '.') }} MT</td>
                        <td class="px-3 py-2 text-right">{{ number_format($operador['total_custo'], 2, ',', '.') }} MT</td>
                        <td class="px-3 py-2 text-right font-medium text-emerald-700">{{ number_format($operador['total_lucro'], 2, ',', '.') }} MT</td>
                        <td class="px-3 py-2 text-right">{{ $operador['num_vendas'] }}</td>
                        <td class="px-3 py-2">
                            <a
                                href="{{ route('operator-reports.detail', array_merge(request()->only(['periodo_inicio', 'periodo_fim', 'registerFilter']), ['operador' => $operador['chave']])) }}"
                                class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50"
                            >
                                {{ __('pages.common.view_details') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-6 text-center text-slate-500">{{ __('pages.operator_reports.no_sales') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
</x-layouts.desktop>
