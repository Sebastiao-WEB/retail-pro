<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Balanço patrimonial</p>
            <p class="text-sm text-slate-500">Elabore o balanço com rubricas automáticas (caixa, stock, resultado) e exporte em PDF.</p>
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
                    <th class="px-3 py-2">Título</th>
                    <th class="px-3 py-2">Data ref.</th>
                    <th class="px-3 py-2">Activo</th>
                    <th class="px-3 py-2">Passivo + CP</th>
                    <th class="px-3 py-2">Estado</th>
                    <th class="px-3 py-2">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($balances as $balance)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 font-medium">{{ $balance->referencia }}</td>
                        <td class="px-3 py-2">{{ $balance->titulo }}</td>
                        <td class="px-3 py-2">{{ $balance->data_referencia->format('d/m/Y') }}</td>
                        <td class="px-3 py-2">{{ number_format((float) $balance->total_activo, 2, ',', '.') }} MT</td>
                        <td class="px-3 py-2">{{ number_format((float) $balance->total_passivo + (float) $balance->total_capital_proprio, 2, ',', '.') }} MT</td>
                        <td class="px-3 py-2">
                            <span class="{{ $balance->status === 'FINALIZED' ? 'text-emerald-600' : 'text-amber-600' }}">
                                {{ $balance->status === 'FINALIZED' ? 'Finalizado' : 'Rascunho' }}
                            </span>
                            @if (! $balance->isBalanced())
                                <span class="ml-1 text-[10px] text-red-600">Desbalanceado</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" wire:click="openEditModal('{{ $balance->id }}')" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">Editar</button>
                                <a href="{{ route('balance-sheets.pdf', $balance) }}" target="_blank" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">PDF</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-6 text-center text-slate-500">Nenhum balanço registado.</td>
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
                        <h3 class="text-base font-semibold">Novo balanço patrimonial</h3>
                    </div>
                    <div class="space-y-3 p-5">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Título</label>
                            <input wire:model.defer="titulo" type="text" class="rp-input">
                            @error('titulo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Data de referência</label>
                            <input wire:model.defer="data_referencia" type="date" class="rp-input">
                            @error('data_referencia') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">Período início</label>
                                <input wire:model.defer="periodo_inicio" type="date" class="rp-input">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">Período fim</label>
                                <input wire:model.defer="periodo_fim" type="date" class="rp-input">
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Notas (opcional)</label>
                            <textarea wire:model.defer="notas" rows="2" class="rp-input"></textarea>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                        <button type="button" wire:click="$set('createModalOpen', false)" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">Cancelar</button>
                        <button type="button" wire:click="criar" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black">Criar e calcular</button>
                    </div>
                </div>
            </div>
        @endif
    @endcan

    @if ($editModalOpen && $balanceEmEdicao)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4">
            <div class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-xl bg-white shadow-xl">
                <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-5 py-3">
                    <div>
                        <h3 class="text-base font-semibold">{{ $balanceEmEdicao->referencia }} — {{ $balanceEmEdicao->titulo }}</h3>
                        <p class="text-xs text-slate-500">
                            Activo: {{ number_format((float) $balanceEmEdicao->total_activo, 2, ',', '.') }} MT ·
                            Passivo + CP: {{ number_format((float) $balanceEmEdicao->total_passivo + (float) $balanceEmEdicao->total_capital_proprio, 2, ',', '.') }} MT
                            @if ($balanceEmEdicao->isBalanced())
                                <span class="text-emerald-600">· Equilibrado</span>
                            @else
                                <span class="text-red-600">· Desbalanceado</span>
                            @endif
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

                    @foreach (['ACTIVO' => 'Activo', 'PASSIVO' => 'Passivo', 'CAPITAL' => 'Capital próprio'] as $secao => $tituloSecao)
                        <div class="overflow-hidden rounded-lg border border-slate-200">
                            <div class="bg-slate-900 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white">{{ $tituloSecao }}</div>
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-left text-[10px] uppercase text-slate-500">
                                    <tr>
                                        <th class="px-3 py-2">Rubrica</th>
                                        <th class="px-3 py-2">Grupo</th>
                                        <th class="px-3 py-2 text-right">Valor (MT)</th>
                                        @can('balance_sheets.manage')
                                            @if (! $balanceEmEdicao->isFinalized())
                                                <th class="px-3 py-2"></th>
                                            @endif
                                        @endcan
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($balanceEmEdicao->lines->where('secao', $secao) as $linha)
                                        <tr class="border-t border-slate-100">
                                            <td class="px-3 py-2">
                                                {{ $linha->rubrika }}
                                                @if ($linha->automatico)
                                                    <span class="text-[10px] text-slate-400">(auto)</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-xs text-slate-500">{{ str_replace('_', ' ', $linha->grupo ?? '—') }}</td>
                                            <td class="px-3 py-2 text-right">
                                                @if ($balanceEmEdicao->isFinalized())
                                                    {{ number_format((float) $linha->valor, 2, ',', '.') }}
                                                @else
                                                    <input
                                                        wire:model.defer="lineValues.{{ $linha->id }}"
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        class="w-28 rounded border border-slate-300 px-2 py-1 text-right text-xs"
                                                    />
                                                @endif
                                            </td>
                                            @can('balance_sheets.manage')
                                                @if (! $balanceEmEdicao->isFinalized() && ! $linha->automatico)
                                                    <td class="px-3 py-2 text-right">
                                                        <button type="button" wire:click="removerLinha('{{ $linha->id }}')" class="text-xs text-red-600 hover:underline">Remover</button>
                                                    </td>
                                                @elseif (! $balanceEmEdicao->isFinalized())
                                                    <td></td>
                                                @endif
                                            @endcan
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endforeach

                    @can('balance_sheets.manage')
                        @if (! $balanceEmEdicao->isFinalized())
                            <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4">
                                <p class="mb-2 text-xs font-semibold text-slate-600">Adicionar rubrica manual</p>
                                <div class="grid grid-cols-1 gap-2 md:grid-cols-4">
                                    <select wire:model.defer="novaSecao" class="rp-input">
                                        <option value="ACTIVO">Activo</option>
                                        <option value="PASSIVO">Passivo</option>
                                        <option value="CAPITAL">Capital próprio</option>
                                    </select>
                                    <input wire:model.defer="novaRubrika" type="text" placeholder="Rubrica" class="rp-input md:col-span-2">
                                    <input wire:model.defer="novaValor" type="number" min="0" step="0.01" class="rp-input">
                                </div>
                                <button type="button" wire:click="adicionarLinhaManual" class="mt-2 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold hover:bg-slate-100">Adicionar rubrica</button>
                            </div>
                        @endif
                    @endcan
                </div>

                <div class="sticky bottom-0 flex flex-wrap justify-end gap-2 border-t border-slate-200 bg-white px-5 py-3">
                    <a href="{{ route('balance-sheets.pdf', $balanceEmEdicao) }}" target="_blank" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        Gerar PDF
                    </a>
                    @can('balance_sheets.manage')
                        @if (! $balanceEmEdicao->isFinalized())
                            <button type="button" wire:click="recalcularAutomaticos" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Recalcular automáticos</button>
                            <button type="button" wire:click="guardarLinhas" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Guardar</button>
                            <button type="button" wire:click="finalizar" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black">Finalizar balanço</button>
                        @endif
                    @endcan
                    <button type="button" wire:click="closeEditModal" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">Fechar</button>
                </div>
            </div>
        </div>
    @endif
</div>
