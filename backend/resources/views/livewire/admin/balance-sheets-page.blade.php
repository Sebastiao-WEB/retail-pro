<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Balanço de fecho</p>
            <p class="text-sm text-slate-500">Fecho do período: recargas de stock, vendas, stock em loja e lucro (preço compra vs venda).</p>
        </div>
        @can('balance_sheets.manage')
            <button type="button" wire:click="openCreateModal" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                <i data-lucide="plus" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
                Novo balanço
            </button>
        @endcan
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <input wire:model.live.debounce.300ms="search" type="text" class="rp-input" placeholder="Pesquisar por referência ou título...">
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">Referência</th>
                    <th class="px-3 py-2">Período</th>
                    <th class="px-3 py-2">Recargas</th>
                    <th class="px-3 py-2">Vendas</th>
                    <th class="px-3 py-2">Lucro</th>
                    <th class="px-3 py-2">Stock (custo)</th>
                    <th class="px-3 py-2">Estado</th>
                    <th class="px-3 py-2">Ações</th>
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
                                {{ $balance->status === 'FINALIZED' ? 'Finalizado' : 'Rascunho' }}
                            </span>
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" wire:click="openEditModal('{{ $balance->id }}')" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">Ver</button>
                                <a href="{{ route('balance-sheets.pdf', $balance) }}" target="_blank" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">PDF</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-6 text-center text-slate-500">Nenhum balanço registado.</td>
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
                        <h3 class="text-base font-semibold">Novo balanço de fecho</h3>
                        <p class="text-xs text-slate-500">Define o período em que o estabelecimento esteve aberto.</p>
                    </div>
                    <div class="space-y-3 p-5">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Título</label>
                            <input wire:model.defer="titulo" type="text" class="rp-input">
                            @error('titulo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Data de fecho</label>
                            <input wire:model.defer="data_referencia" type="date" class="rp-input">
                            @error('data_referencia') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">Período início</label>
                                <input wire:model.defer="periodo_inicio" type="date" class="rp-input">
                                @error('periodo_inicio') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">Período fim</label>
                                <input wire:model.defer="periodo_fim" type="date" class="rp-input">
                                @error('periodo_fim') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Notas (opcional)</label>
                            <textarea wire:model.defer="notas" rows="2" class="rp-input"></textarea>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                        <button type="button" wire:click="$set('createModalOpen', false)" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">Cancelar</button>
                        <button type="button" wire:click="criar" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black">Calcular balanço</button>
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
                            Período: {{ optional($balanceEmEdicao->periodo_inicio)->format('d/m/Y') }} a {{ optional($balanceEmEdicao->periodo_fim)->format('d/m/Y') }}
                            · Fecho: {{ $balanceEmEdicao->data_referencia->format('d/m/Y') }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeEditModal" class="text-slate-500 hover:text-slate-800">✕</button>
                </div>

                <div class="space-y-4 p-5">
                    @if (! $balanceEmEdicao->isFinalized())
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">Título</label>
                                <input wire:model.defer="titulo" type="text" class="rp-input">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">Notas</label>
                                <input wire:model.defer="notas" type="text" class="rp-input">
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[10px] uppercase text-slate-500">Recargas</p>
                            <p class="text-sm font-semibold">{{ number_format((float) $balanceEmEdicao->total_recargas_valor, 2, ',', '.') }} MT</p>
                            <p class="text-[10px] text-slate-500">{{ number_format((float) $balanceEmEdicao->total_recargas_qtd, 0, ',', '.') }} un.</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[10px] uppercase text-slate-500">Vendas</p>
                            <p class="text-sm font-semibold">{{ number_format((float) $balanceEmEdicao->total_vendas_valor, 2, ',', '.') }} MT</p>
                            <p class="text-[10px] text-slate-500">{{ number_format((float) $balanceEmEdicao->total_vendas_qtd, 0, ',', '.') }} un.</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[10px] uppercase text-slate-500">Custo vendido</p>
                            <p class="text-sm font-semibold">{{ number_format((float) $balanceEmEdicao->total_custo_vendas, 2, ',', '.') }} MT</p>
                        </div>
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                            <p class="text-[10px] uppercase text-emerald-700">Lucro período</p>
                            <p class="text-sm font-semibold text-emerald-800">{{ number_format((float) $balanceEmEdicao->total_lucro, 2, ',', '.') }} MT</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[10px] uppercase text-slate-500">Stock (custo)</p>
                            <p class="text-sm font-semibold">{{ number_format((float) $balanceEmEdicao->total_stock_valor_compra, 2, ',', '.') }} MT</p>
                            <p class="text-[10px] text-slate-500">{{ number_format((float) $balanceEmEdicao->total_stock_qtd, 0, ',', '.') }} un.</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[10px] uppercase text-slate-500">Stock (venda)</p>
                            <p class="text-sm font-semibold">{{ number_format((float) $balanceEmEdicao->total_stock_valor_venda, 2, ',', '.') }} MT</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-slate-200">
                        <table class="min-w-full text-xs">
                            <thead class="bg-slate-900 text-left uppercase tracking-wide text-white">
                                <tr>
                                    <th class="px-3 py-2">Produto</th>
                                    <th class="px-3 py-2">Cód. barras</th>
                                    <th class="px-3 py-2 text-right">Rec. qtd</th>
                                    <th class="px-3 py-2 text-right">Rec. valor</th>
                                    <th class="px-3 py-2 text-right">Vend. qtd</th>
                                    <th class="px-3 py-2 text-right">Vend. valor</th>
                                    <th class="px-3 py-2 text-right">Custo</th>
                                    <th class="px-3 py-2 text-right">Lucro</th>
                                    <th class="px-3 py-2 text-right">Stock qtd</th>
                                    <th class="px-3 py-2 text-right">Stock custo</th>
                                    <th class="px-3 py-2 text-right">Stock venda</th>
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
                                        <td colspan="11" class="px-3 py-6 text-center text-slate-500">Sem movimentos no período seleccionado.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if ($balanceEmEdicao->lines->isNotEmpty())
                                <tfoot class="bg-slate-50 font-semibold">
                                    <tr class="border-t border-slate-200">
                                        <td class="px-3 py-2">Totais</td>
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
                                Stock por localização (auditoria)
                            </div>
                            @foreach ($balanceEmEdicao->locationLines->groupBy('location_id') as $linhasLocal)
                                @php $cabecalho = $linhasLocal->first(); @endphp
                                <div class="border-t border-slate-200">
                                    <div class="flex flex-wrap items-center justify-between gap-2 bg-slate-50 px-3 py-2 text-xs">
                                        <span class="font-semibold">{{ $cabecalho->local_codigo }} — {{ $cabecalho->local_nome }}</span>
                                        <span class="text-slate-600">
                                            {{ number_format((float) $linhasLocal->sum('quantity'), 0, ',', '.') }} un. ·
                                            {{ number_format((float) $linhasLocal->sum('valor_compra'), 2, ',', '.') }} MT (custo)
                                        </span>
                                    </div>
                                    <table class="min-w-full text-xs">
                                        <thead class="bg-slate-100 text-left uppercase tracking-wide text-slate-500">
                                            <tr>
                                                <th class="px-3 py-2">Produto</th>
                                                <th class="px-3 py-2">Cód. barras</th>
                                                <th class="px-3 py-2 text-right">Qtd</th>
                                                <th class="px-3 py-2 text-right">Valor custo</th>
                                                <th class="px-3 py-2 text-right">Valor venda</th>
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
                        Gerar PDF
                    </a>
                    @can('balance_sheets.manage')
                        @if (! $balanceEmEdicao->isFinalized())
                            <button type="button" wire:click="recalcular" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Recalcular</button>
                            <button type="button" wire:click="guardar" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Guardar</button>
                            <button type="button" wire:click="finalizar" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black">Finalizar balanço</button>
                        @endif
                    @endcan
                    <button type="button" wire:click="closeEditModal" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">Fechar</button>
                </div>
            </div>
        </div>
    @endif
</div>
