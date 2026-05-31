<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.operator_reports.title') }}</p>
            <p class="text-sm text-slate-500">{{ __('pages.operator_reports.subtitle') }}</p>
        </div>
        <a href="{{ $this->pdfUrl() }}" target="_blank" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
            <i data-lucide="file-down" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
            {{ __('pages.common.generate_pdf') }}
        </a>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.period_start') }}</label>
                <input wire:model.blur="periodo_inicio" type="date" class="rp-input">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.period_end') }}</label>
                <input wire:model.blur="periodo_fim" type="date" class="rp-input">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.register') }}</label>
                <select wire:model.live="registerFilter" class="rp-input">
                    <option value="">{{ __('app.all_registers') }}</option>
                    @foreach ($registers as $register)
                        <option value="{{ $register->id }}">{{ $register->name }} ({{ $register->code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="button" wire:click="aplicarPeriodoMes" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold hover:bg-slate-50">{{ __('pages.common.this_month') }}</button>
                <button type="button" wire:click="aplicarPeriodoMesAnterior" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold hover:bg-slate-50">{{ __('pages.common.previous_month') }}</button>
            </div>
        </div>
    </div>

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
                            <button type="button" wire:click="openOperadorDetalhe('{{ $operador['chave'] }}')" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">
                                {{ __('pages.common.view_details') }}
                            </button>
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

    @if ($operadorSelecionado)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4">
            <div class="max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-xl bg-white shadow-xl">
                <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-5 py-3">
                    <div>
                        <h3 class="text-base font-semibold">{{ $operadorSelecionado['nome'] }}</h3>
                        <p class="text-xs text-slate-500">
                            {{ __('pages.operator_reports.operator_sales_summary', [
                                'sales' => number_format($operadorSelecionado['total_vendas'], 2, ',', '.'),
                                'profit' => number_format($operadorSelecionado['total_lucro'], 2, ',', '.'),
                                'count' => __('pages.common.sales_count', ['count' => $operadorSelecionado['num_vendas']]),
                            ]) }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeOperadorDetalhe" class="text-slate-500 hover:text-slate-800">✕</button>
                </div>

                <div class="space-y-4 p-5">
                    @foreach ($operadorSelecionado['vendas'] as $venda)
                        <div class="overflow-hidden rounded-lg border border-slate-200">
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
                    @endforeach
                </div>

                <div class="sticky bottom-0 flex justify-end border-t border-slate-200 bg-white px-5 py-3">
                    <button type="button" wire:click="closeOperadorDetalhe" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">{{ __('app.close') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
