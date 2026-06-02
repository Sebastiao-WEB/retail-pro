<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.sales.title') }}</p>
            <p class="text-sm text-slate-500">{{ __('pages.sales.subtitle') }}</p>
            <p class="mt-1 text-xs font-semibold text-emerald-700">
                {{ __('pages.sales.filtered_total') }}: {{ number_format($totalFiltrado, 2, ',', '.') }} MT
            </p>
        </div>
        @can('sales.export')
            <button type="button" wire:click="exportCsv" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                <i data-lucide="download" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
                {{ __('pages.sales.export_csv') }}
            </button>
        @endcan
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            <div class="md:col-span-2 xl:col-span-3">
                <input wire:model.live.debounce.300ms="search" type="text" class="rp-input" placeholder="{{ __('pages.sales.search_placeholder') }}">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.register') }}</label>
                <select wire:model.live="registerFilter" class="rp-input">
                    <option value="">{{ __('app.all') }}</option>
                    @foreach ($registers as $register)
                        <option value="{{ $register->id }}">{{ $register->code }} — {{ $register->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.status') }}</label>
                <select wire:model.live="estadoFilter" class="rp-input">
                    <option value="">{{ __('app.all') }}</option>
                    <option value="Concluida">{{ \App\Support\Translations::saleStatus('Concluida') }}</option>
                    <option value="Revertida">{{ \App\Support\Translations::saleStatus('Revertida') }}</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.payment') }}</label>
                <select wire:model.live="pagamentoFilter" class="rp-input">
                    <option value="">{{ __('app.all') }}</option>
                    <option value="Dinheiro">{{ \App\Support\Translations::paymentMethod('Dinheiro') }}</option>
                    <option value="Transferência">{{ \App\Support\Translations::paymentMethod('Transferência') }}</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.date_from') }}</label>
                <input wire:model.blur="dateFrom" type="date" class="rp-input">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.date_to') }}</label>
                <input wire:model.blur="dateTo" type="date" class="rp-input">
            </div>
            <div class="flex items-end">
                <button type="button" wire:click="limparFiltros" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                    {{ __('app.clear_filters') }}
                </button>
            </div>
        </div>
    </div>

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
                            <button type="button" wire:click="openDetail('{{ $venda->id }}')" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">{{ __('app.details') }}</button>
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

    @if ($detailModalOpen && $detalhe)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4">
            <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                    <h3 class="text-base font-semibold">{{ __('pages.sales.detail_title', ['reference' => $detalhe->referencia]) }}</h3>
                    <button type="button" wire:click="closeDetail" class="text-slate-500 hover:text-slate-800">✕</button>
                </div>
                <div class="space-y-4 p-5 text-sm">
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                        <p><strong>{{ __('pages.sales.detail_client') }}:</strong> {{ $detalhe->cliente }}</p>
                        <p><strong>{{ __('pages.sales.detail_operator') }}:</strong> {{ $detalhe->operador ?? $detalhe->user?->name ?? '—' }}</p>
                        <p><strong>{{ __('pages.sales.detail_register') }}:</strong> {{ $detalhe->caixa ?? $detalhe->register?->name ?? '—' }}</p>
                        <p><strong>{{ __('pages.sales.detail_payment') }}:</strong> {{ \App\Support\Translations::paymentMethod($detalhe->metodo_pagamento) }}</p>
                        <p><strong>{{ __('pages.sales.detail_status') }}:</strong> {{ \App\Support\Translations::saleStatus($detalhe->estado) }}</p>
                        <p><strong>{{ __('pages.sales.detail_date') }}:</strong> {{ optional($detalhe->data)->format('d/m/Y H:i') ?? '—' }}</p>
                        <p><strong>{{ __('pages.sales.detail_subtotal') }}:</strong> {{ number_format((float) $detalhe->subtotal, 2, ',', '.') }} MT</p>
                        <p><strong>{{ __('pages.sales.detail_total_iva') }}:</strong> {{ number_format((float) $detalhe->itens->sum(fn ($item) => $item->ivaTotalLinha()), 2, ',', '.') }} MT</p>
                        <p><strong>{{ __('pages.sales.detail_discount') }}:</strong> {{ number_format((float) $detalhe->desconto_aplicado, 2, ',', '.') }} MT</p>
                        <p><strong>{{ __('pages.sales.detail_total') }}:</strong> {{ number_format((float) $detalhe->total, 2, ',', '.') }} MT</p>
                        @if (strcasecmp($detalhe->metodo_pagamento, 'Dinheiro') === 0)
                            <p><strong>{{ __('pages.sales.detail_amount_paid') }}:</strong> {{ number_format((float) $detalhe->valor_pago, 2, ',', '.') }} MT</p>
                            <p><strong>{{ __('pages.sales.detail_change') }}:</strong> {{ number_format((float) $detalhe->troco, 2, ',', '.') }} MT</p>
                        @endif
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-slate-200">
                        <table class="min-w-full text-xs">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-2 py-2 text-left">{{ __('pages.common.item') }}</th>
                                    <th class="px-2 py-2 text-right">{{ __('pages.common.qty_short') }}</th>
                                    <th class="px-2 py-2 text-right">{{ __('pages.common.price') }}</th>
                                    <th class="px-2 py-2 text-right">{{ __('app.fields.iva') }} %</th>
                                    <th class="px-2 py-2 text-right">{{ __('app.fields.iva_line_total') }}</th>
                                    <th class="px-2 py-2 text-right">{{ __('pages.common.subtotal') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($detalhe->itens as $item)
                                    @php($tax = $item->resolvedTax())
                                    <tr class="border-t border-slate-100">
                                        <td class="px-2 py-2">{{ $item->nome }}</td>
                                        <td class="px-2 py-2 text-right">{{ number_format((float) $item->quantidade, 2, ',', '.') }}</td>
                                        <td class="px-2 py-2 text-right">{{ number_format((float) $item->preco_venda, 2, ',', '.') }}</td>
                                        <td class="px-2 py-2 text-right">{{ number_format((float) $tax['ivaPercentual'], 2, ',', '.') }}%</td>
                                        <td class="px-2 py-2 text-right">{{ number_format($item->ivaTotalLinha(), 2, ',', '.') }} MT</td>
                                        <td class="px-2 py-2 text-right">{{ number_format((float) $item->subtotal, 2, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-2 py-4 text-center text-slate-500">{{ __('pages.common.no_items') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
