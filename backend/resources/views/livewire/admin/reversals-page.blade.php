<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Solicitações de reversão</p>
            <p class="text-sm text-slate-500">Acompanhamento de pedidos de cancelamento de venda. Use Gerar PDF para o relatório por intervalos.</p>
        </div>
        <a href="{{ $this->pdfUrl() }}" target="_blank" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
            <i data-lucide="file-down" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
            Gerar PDF
        </a>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Período início</label>
                <input wire:model.live="periodo_inicio" type="date" class="rp-input">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Período fim</label>
                <input wire:model.live="periodo_fim" type="date" class="rp-input">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Caixa</label>
                <select wire:model.live="registerFilter" class="rp-input">
                    <option value="">Todos os caixas</option>
                    @foreach ($registers as $register)
                        <option value="{{ $register->id }}">{{ $register->name }} ({{ $register->code }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Estado</label>
                <select wire:model.live="statusFilter" class="rp-input">
                    <option value="">Todos os estados</option>
                    <option value="PENDING">PENDENTE</option>
                    <option value="APPROVED">APROVADO</option>
                    <option value="REJECTED">REJEITADO</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="button" wire:click="aplicarPeriodoMes" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold hover:bg-slate-50">Este mês</button>
                <button type="button" wire:click="aplicarPeriodoMesAnterior" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold hover:bg-slate-50">Mês anterior</button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
        <div class="md:col-span-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Resumo do período seleccionado</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-[10px] uppercase text-slate-500">Total</p>
            <p class="text-lg font-semibold">{{ number_format($totais['total'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
            <p class="text-[10px] uppercase text-amber-700">Pendentes</p>
            <p class="text-lg font-semibold text-amber-800">{{ number_format($totais['pendentes'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-[10px] uppercase text-emerald-700">Aprovadas</p>
            <p class="text-lg font-semibold text-emerald-800">{{ number_format($totais['aprovadas'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg border border-red-200 bg-red-50 p-4">
            <p class="text-[10px] uppercase text-red-700">Rejeitadas</p>
            <p class="text-lg font-semibold text-red-800">{{ number_format($totais['rejeitadas'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-[10px] uppercase text-slate-500">Valor revertido</p>
            <p class="text-lg font-semibold">{{ number_format($totais['valor_revertido'], 2, ',', '.') }} MT</p>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <input wire:model.live.debounce.300ms="search" type="text" class="rp-input" placeholder="Pesquisar por referência, operador, venda ou motivo...">
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">Referência</th>
                    <th class="px-3 py-2">Estado</th>
                    <th class="px-3 py-2">Operador</th>
                    <th class="px-3 py-2 text-right">Valor</th>
                    <th class="px-3 py-2">Motivo</th>
                    <th class="px-3 py-2">Solicitado em</th>
                    <th class="px-3 py-2">Decidido em</th>
                    @can('reversals.manage')
                        <th class="px-3 py-2">Ações</th>
                    @endcan
                </tr>
            </thead>
            <tbody>
                @forelse ($reversoes as $reversao)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 font-medium">{{ $reversao->sale?->referencia ?? $reversao->sale_id }}</td>
                        <td class="px-3 py-2">
                            <span class="@if($reversao->status === 'APPROVED') text-emerald-600 @elseif($reversao->status === 'REJECTED') text-red-600 @else text-amber-600 @endif">
                                {{ $reversao->status }}
                            </span>
                        </td>
                        <td class="px-3 py-2">{{ $reversao->sale?->operador ?? '—' }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) ($reversao->sale?->total ?? 0), 2, ',', '.') }} MT</td>
                        <td class="px-3 py-2">{{ $reversao->reason ?: '---' }}</td>
                        <td class="px-3 py-2">{{ optional($reversao->requested_at)->format('d/m/Y H:i') }}</td>
                        <td class="px-3 py-2">{{ optional($reversao->decided_at)->format('d/m/Y H:i') ?: '---' }}</td>
                        @can('reversals.manage')
                            <td class="px-3 py-2">
                                @if ($reversao->status === 'PENDING')
                                    <div class="flex items-center gap-2">
                                        <button type="button" wire:click="openDecisionModal('{{ $reversao->id }}', 'APPROVED')" class="rounded-md border border-emerald-200 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50">
                                            <i data-lucide="check" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
                                            Aprovar
                                        </button>
                                        <button type="button" wire:click="openDecisionModal('{{ $reversao->id }}', 'REJECTED')" class="rounded-md border border-red-200 px-2 py-1 text-xs text-red-600 hover:bg-red-50">
                                            <i data-lucide="x" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
                                            Rejeitar
                                        </button>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400">Concluído</span>
                                @endif
                            </td>
                        @endcan
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ auth()->user()?->can('reversals.manage') ? 8 : 7 }}" class="px-3 py-6 text-center text-slate-500">
                            @if ($search !== '' || $statusFilter !== '' || $registerFilter !== '')
                                Nenhum resultado para os filtros seleccionados.
                            @else
                                Sem solicitações de reversão.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($reversoes->hasPages())
        <div>{{ $reversoes->links() }}</div>
    @endif

    @can('reversals.manage')
    @if ($decisionModalOpen)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4">
            <div class="w-full max-w-lg rounded-xl bg-white shadow-xl">
                <div class="border-b border-slate-200 px-5 py-3">
                    <h3 class="text-base font-semibold text-slate-900">
                        {{ $decisionStatus === 'APPROVED' ? 'Aprovar solicitação' : 'Rejeitar solicitação' }}
                    </h3>
                </div>
                <div class="p-5">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Motivo (opcional)</label>
                    <textarea wire:model.defer="decisionReason" rows="4" class="rp-input" placeholder="Adicionar observação da decisão..."></textarea>
                    @error('decisionReason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                    <button type="button" wire:click="$set('decisionModalOpen', false)" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-100"><i data-lucide="x" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>Cancelar</button>
                    <button type="button" wire:click="applyDecision" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95"><i data-lucide="check-check" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>Confirmar</button>
                </div>
            </div>
        </div>
    @endif
    @endcan
</div>
