<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Histórico de vendas</p>
            <p class="text-sm text-slate-500">Monitorização das vendas concluídas no POS.</p>
            <p class="mt-1 text-xs font-semibold text-emerald-700">
                Total filtrado: {{ number_format($totalFiltrado, 2, ',', '.') }} MT
            </p>
        </div>
        <button type="button" wire:click="exportCsv" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
            <i data-lucide="download" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
            Exportar CSV
        </button>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            <div class="md:col-span-2 xl:col-span-3">
                <input wire:model.live.debounce.300ms="search" type="text" class="rp-input" placeholder="Pesquisar por referência, cliente, caixa ou operador...">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Caixa</label>
                <select wire:model.live="registerFilter" class="rp-input">
                    <option value="">Todos</option>
                    @foreach ($registers as $register)
                        <option value="{{ $register->id }}">{{ $register->code }} — {{ $register->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Estado</label>
                <select wire:model.live="estadoFilter" class="rp-input">
                    <option value="">Todos</option>
                    <option value="Concluida">Concluída</option>
                    <option value="Revertida">Revertida</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Pagamento</label>
                <select wire:model.live="pagamentoFilter" class="rp-input">
                    <option value="">Todos</option>
                    <option value="Dinheiro">Dinheiro</option>
                    <option value="Transferência">Transferência</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Data inicial</label>
                <input wire:model.live="dateFrom" type="date" class="rp-input">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Data final</label>
                <input wire:model.live="dateTo" type="date" class="rp-input">
            </div>
            <div class="flex items-end">
                <button type="button" wire:click="limparFiltros" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                    Limpar filtros
                </button>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">Referência</th>
                    <th class="px-3 py-2">Cliente</th>
                    <th class="px-3 py-2">Caixa</th>
                    <th class="px-3 py-2">Pagamento</th>
                    <th class="px-3 py-2">Estado</th>
                    <th class="px-3 py-2">Total</th>
                    <th class="px-3 py-2">Data</th>
                    <th class="px-3 py-2">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($vendas as $venda)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 font-medium">{{ $venda->referencia }}</td>
                        <td class="px-3 py-2">{{ $venda->cliente }}</td>
                        <td class="px-3 py-2">{{ $venda->caixa ?? $venda->register?->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $venda->metodo_pagamento }}</td>
                        <td class="px-3 py-2">
                            <span class="{{ strcasecmp($venda->estado, 'Revertida') === 0 ? 'text-red-600' : 'text-slate-700' }}">{{ $venda->estado }}</span>
                        </td>
                        <td class="px-3 py-2">{{ number_format((float) $venda->total, 2, ',', '.') }} MT</td>
                        <td class="px-3 py-2">{{ optional($venda->data)->format('d/m/Y H:i') }}</td>
                        <td class="px-3 py-2">
                            <button type="button" wire:click="openDetail('{{ $venda->id }}')" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">Detalhes</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-6 text-center text-slate-500">Sem vendas registadas.</td>
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
                    <h3 class="text-base font-semibold">Venda {{ $detalhe->referencia }}</h3>
                    <button type="button" wire:click="closeDetail" class="text-slate-500 hover:text-slate-800">✕</button>
                </div>
                <div class="space-y-4 p-5 text-sm">
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                        <p><strong>Cliente:</strong> {{ $detalhe->cliente }}</p>
                        <p><strong>Operador:</strong> {{ $detalhe->operador ?? $detalhe->user?->name ?? '—' }}</p>
                        <p><strong>Caixa:</strong> {{ $detalhe->caixa ?? $detalhe->register?->name ?? '—' }}</p>
                        <p><strong>Pagamento:</strong> {{ $detalhe->metodo_pagamento }}</p>
                        <p><strong>Estado:</strong> {{ $detalhe->estado }}</p>
                        <p><strong>Data:</strong> {{ optional($detalhe->data)->format('d/m/Y H:i') ?? '—' }}</p>
                        <p><strong>Subtotal:</strong> {{ number_format((float) $detalhe->subtotal, 2, ',', '.') }} MT</p>
                        <p><strong>Desconto:</strong> {{ number_format((float) $detalhe->desconto_aplicado, 2, ',', '.') }} MT</p>
                        <p><strong>Total:</strong> {{ number_format((float) $detalhe->total, 2, ',', '.') }} MT</p>
                        @if (strcasecmp($detalhe->metodo_pagamento, 'Dinheiro') === 0)
                            <p><strong>Valor pago:</strong> {{ number_format((float) $detalhe->valor_pago, 2, ',', '.') }} MT</p>
                            <p><strong>Troco:</strong> {{ number_format((float) $detalhe->troco, 2, ',', '.') }} MT</p>
                        @endif
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-slate-200">
                        <table class="min-w-full text-xs">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-2 py-2 text-left">Item</th>
                                    <th class="px-2 py-2 text-right">Qtd</th>
                                    <th class="px-2 py-2 text-right">Preço</th>
                                    <th class="px-2 py-2 text-right">IVA %</th>
                                    <th class="px-2 py-2 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($detalhe->itens as $item)
                                    <tr class="border-t border-slate-100">
                                        <td class="px-2 py-2">{{ $item->nome }}</td>
                                        <td class="px-2 py-2 text-right">{{ number_format((float) $item->quantidade, 2, ',', '.') }}</td>
                                        <td class="px-2 py-2 text-right">{{ number_format((float) $item->preco_venda, 2, ',', '.') }}</td>
                                        <td class="px-2 py-2 text-right">{{ number_format((float) $item->iva_percentual, 0) }}%</td>
                                        <td class="px-2 py-2 text-right">{{ number_format((float) $item->subtotal, 2, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-2 py-4 text-center text-slate-500">Sem itens.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
