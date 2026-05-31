<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.balance_sheets.title') }}</p>
            <p class="text-sm text-slate-500">{{ __('pages.balance_sheets.subtitle') }}</p>
        </div>
        @can('balance_sheets.manage')
            <button type="button" wire:click="openCreateModal" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                <i data-lucide="plus" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
                {{ __('pages.balance_sheets.new') }}
            </button>
        @endcan
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <input wire:model.live.debounce.300ms="search" type="text" class="rp-input" placeholder="{{ __('pages.balance_sheets.search_placeholder') }}">
    </div>

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
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" wire:click="openEditModal('{{ $balance->id }}')" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">{{ __('pages.common.view') }}</button>
                                <a href="{{ route('balance-sheets.pdf', $balance) }}" target="_blank" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">PDF</a>
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
        @if ($createModalOpen)
            <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4">
                <div class="w-full max-w-lg rounded-xl bg-white shadow-xl">
                    <div class="border-b border-slate-200 px-5 py-3">
                        <h3 class="text-base font-semibold">{{ __('pages.balance_sheets.create_title') }}</h3>
                        <p class="text-xs text-slate-500">{{ __('pages.balance_sheets.create_subtitle') }}</p>
                    </div>
                    <div class="space-y-3 p-5">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.title_label') }}</label>
                            <input wire:model.defer="titulo" type="text" class="rp-input">
                            @error('titulo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.closing_date') }}</label>
                            <input wire:model.defer="data_referencia" type="date" class="rp-input">
                            @error('data_referencia') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.period_start') }}</label>
                                <input wire:model.defer="periodo_inicio" type="date" class="rp-input">
                                @error('periodo_inicio') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.period_end') }}</label>
                                <input wire:model.defer="periodo_fim" type="date" class="rp-input">
                                @error('periodo_fim') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.notes_optional') }}</label>
                            <textarea wire:model.defer="notas" rows="2" class="rp-input"></textarea>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                        <button type="button" wire:click="$set('createModalOpen', false)" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">{{ __('app.cancel') }}</button>
                        <button type="button" wire:click="criar" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black">{{ __('pages.common.calculate_balance') }}</button>
                    </div>
                </div>
            </div>
        @endif
    @endcan

    @if ($editModalOpen && $balanceEmEdicao)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4">
            <div class="max-h-[92vh] w-full max-w-6xl overflow-y-auto rounded-xl bg-white shadow-xl">
                <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-5 py-3">
                    <div>
                        <h3 class="text-base font-semibold">{{ $balanceEmEdicao->referencia }} — {{ $balanceEmEdicao->titulo }}</h3>
                        <p class="text-xs text-slate-500">
                            {{ __('pages.common.period_range', ['from' => optional($balanceEmEdicao->periodo_inicio)->format('d/m/Y'), 'to' => optional($balanceEmEdicao->periodo_fim)->format('d/m/Y')]) }}
                            {{ __('pages.common.closing_range', ['date' => $balanceEmEdicao->data_referencia->format('d/m/Y')]) }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeEditModal" class="text-slate-500 hover:text-slate-800">✕</button>
                </div>

                <div class="space-y-4 p-5">
                    @if (! $balanceEmEdicao->isFinalized())
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.title_label') }}</label>
                                <input wire:model.defer="titulo" type="text" class="rp-input">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.note') }}</label>
                                <input wire:model.defer="notas" type="text" class="rp-input">
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[10px] uppercase text-slate-500">{{ __('pages.common.reloads') }}</p>
                            <p class="text-sm font-semibold">{{ number_format((float) $balanceEmEdicao->total_recargas_valor, 2, ',', '.') }} MT</p>
                            <p class="text-[10px] text-slate-500">{{ number_format((float) $balanceEmEdicao->total_recargas_qtd, 0, ',', '.') }} {{ __('pages.products.units') }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[10px] uppercase text-slate-500">{{ __('pages.common.sales_amount') }}</p>
                            <p class="text-sm font-semibold">{{ number_format((float) $balanceEmEdicao->total_vendas_valor, 2, ',', '.') }} MT</p>
                            <p class="text-[10px] text-slate-500">{{ number_format((float) $balanceEmEdicao->total_vendas_qtd, 0, ',', '.') }} {{ __('pages.products.units') }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[10px] uppercase text-slate-500">{{ __('pages.common.cost_sold') }}</p>
                            <p class="text-sm font-semibold">{{ number_format((float) $balanceEmEdicao->total_custo_vendas, 2, ',', '.') }} MT</p>
                        </div>
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                            <p class="text-[10px] uppercase text-emerald-700">{{ __('pages.common.period_profit') }}</p>
                            <p class="text-sm font-semibold text-emerald-800">{{ number_format((float) $balanceEmEdicao->total_lucro, 2, ',', '.') }} MT</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[10px] uppercase text-slate-500">{{ __('pages.common.stock_cost') }}</p>
                            <p class="text-sm font-semibold">{{ number_format((float) $balanceEmEdicao->total_stock_valor_compra, 2, ',', '.') }} MT</p>
                            <p class="text-[10px] text-slate-500">{{ number_format((float) $balanceEmEdicao->total_stock_qtd, 0, ',', '.') }} {{ __('pages.products.units') }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[10px] uppercase text-slate-500">{{ __('pages.common.stock_sale') }}</p>
                            <p class="text-sm font-semibold">{{ number_format((float) $balanceEmEdicao->total_stock_valor_venda, 2, ',', '.') }} MT</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-slate-200">
                        <table class="min-w-full text-xs">
                            <thead class="bg-slate-900 text-left uppercase tracking-wide text-white">
                                <tr>
                                    <th class="px-3 py-2">{{ __('app.fields.product') }}</th>
                                    <th class="px-3 py-2">{{ __('pages.common.barcode_short') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('pages.common.reload_qty') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('pages.common.reload_value') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('pages.common.sold_qty') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('pages.common.sold_value') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('pages.common.cost') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('pages.common.profit') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('pages.common.stock_qty') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('pages.common.stock_cost_short') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('pages.common.stock_sale_short') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($balanceEmEdicao->lines as $linha)
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
                                        <td colspan="11" class="px-3 py-6 text-center text-slate-500">{{ __('pages.common.no_movements_period') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if ($balanceEmEdicao->lines->isNotEmpty())
                                <tfoot class="bg-slate-50 font-semibold">
                                    <tr class="border-t border-slate-200">
                                        <td class="px-3 py-2">{{ __('pages.common.totals') }}</td>
                                        <td class="px-3 py-2"></td>
                                        <td class="px-3 py-2 text-right">{{ number_format((float) $balanceEmEdicao->total_recargas_qtd, 0, ',', '.') }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format((float) $balanceEmEdicao->total_recargas_valor, 2, ',', '.') }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format((float) $balanceEmEdicao->total_vendas_qtd, 0, ',', '.') }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format((float) $balanceEmEdicao->total_vendas_valor, 2, ',', '.') }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format((float) $balanceEmEdicao->total_custo_vendas, 2, ',', '.') }}</td>
                                        <td class="px-3 py-2 text-right text-emerald-700">{{ number_format((float) $balanceEmEdicao->total_lucro, 2, ',', '.') }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format((float) $balanceEmEdicao->total_stock_qtd, 0, ',', '.') }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format((float) $balanceEmEdicao->total_stock_valor_compra, 2, ',', '.') }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format((float) $balanceEmEdicao->total_stock_valor_venda, 2, ',', '.') }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>

                    @if ($balanceEmEdicao->locationLines->isNotEmpty())
                        <div class="overflow-hidden rounded-lg border border-slate-200">
                            <div class="bg-slate-800 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white">
                                {{ __('pages.common.stock_by_location_audit') }}
                            </div>
                            @foreach ($balanceEmEdicao->locationLines->groupBy('location_id') as $linhasLocal)
                                @php $cabecalho = $linhasLocal->first(); @endphp
                                <div class="border-t border-slate-200">
                                    <div class="flex flex-wrap items-center justify-between gap-2 bg-slate-50 px-3 py-2 text-xs">
                                        <span class="font-semibold">{{ $cabecalho->local_codigo }} — {{ $cabecalho->local_nome }}</span>
                                        <span class="text-slate-600">
                                            {{ number_format((float) $linhasLocal->sum('quantity'), 0, ',', '.') }} {{ __('pages.products.units') }} ·
                                            {{ number_format((float) $linhasLocal->sum('valor_compra'), 2, ',', '.') }} MT ({{ __('pages.common.cost_short') }})
                                        </span>
                                    </div>
                                    <table class="min-w-full text-xs">
                                        <thead class="bg-slate-100 text-left uppercase tracking-wide text-slate-500">
                                            <tr>
                                                <th class="px-3 py-2">{{ __('app.fields.product') }}</th>
                                                <th class="px-3 py-2">{{ __('pages.common.barcode_short') }}</th>
                                                <th class="px-3 py-2 text-right">{{ __('pages.common.qty_short') }}</th>
                                                <th class="px-3 py-2 text-right">{{ __('pages.common.cost_value') }}</th>
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
                </div>

                <div class="sticky bottom-0 flex flex-wrap justify-end gap-2 border-t border-slate-200 bg-white px-5 py-3">
                    <a href="{{ route('balance-sheets.pdf', $balanceEmEdicao) }}" target="_blank" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        {{ __('pages.common.generate_pdf') }}
                    </a>
                    @can('balance_sheets.manage')
                        @if (! $balanceEmEdicao->isFinalized())
                            <button type="button" wire:click="recalcular" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ __('pages.common.recalculate') }}</button>
                            <button type="button" wire:click="guardar" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ __('app.save') }}</button>
                            <button type="button" wire:click="finalizar" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black">{{ __('pages.common.finalize') }}</button>
                        @endif
                    @endcan
                    <button type="button" wire:click="closeEditModal" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">{{ __('app.close') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
