<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Compras e abastecimento</p>
            <p class="text-sm text-slate-500">Entradas de stock registadas pelo sistema.</p>
        </div>
        @can('purchases.manage')
            <button type="button" wire:click="openCreateModal" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                <i data-lucide="plus" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
                Nova compra
            </button>
        @endcan
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <input wire:model.live.debounce.300ms="search" type="text" class="rp-input" placeholder="Pesquisar por fornecedor...">
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">Fornecedor</th>
                    <th class="px-3 py-2">Itens</th>
                    <th class="px-3 py-2">Total</th>
                    <th class="px-3 py-2">Data</th>
                    @can('purchases.manage')
                        <th class="px-3 py-2">Ações</th>
                    @endcan
                </tr>
            </thead>
            <tbody>
                @forelse ($compras as $compra)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 font-medium">{{ $compra->fornecedor }}</td>
                        <td class="px-3 py-2">{{ is_array($compra->itens) ? count($compra->itens) : 0 }}</td>
                        <td class="px-3 py-2">{{ number_format((float) $compra->total, 2, ',', '.') }} MZN</td>
                        <td class="px-3 py-2">{{ optional($compra->data)->format('d/m/Y H:i') }}</td>
                        @can('purchases.manage')
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <button type="button" wire:click="openEditModal('{{ $compra->id }}')" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50"><i data-lucide="pencil" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>Editar</button>
                                    <button type="button" wire:click="confirmDelete('{{ $compra->id }}')" class="rounded-md border border-red-200 px-2 py-1 text-xs text-red-600 hover:bg-red-50"><i data-lucide="trash-2" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>Remover</button>
                                </div>
                            </td>
                        @endcan
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ auth()->user()?->can('purchases.manage') ? 5 : 4 }}" class="px-3 py-6 text-center text-slate-500">Sem compras registadas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $compras->links() }}</div>

    @can('purchases.manage')
    @if ($modalOpen)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4">
            <div class="w-full max-w-2xl rounded-xl bg-white shadow-xl">
                <div class="border-b border-slate-200 px-5 py-3">
                    <h3 class="text-base font-semibold text-slate-900">{{ $editingId ? 'Editar compra' : 'Nova compra' }}</h3>
                </div>
                <div class="grid grid-cols-1 gap-3 p-5">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Fornecedor</label>
                        <input wire:model.defer="fornecedor" type="text" class="rp-input">
                        @error('fornecedor') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Total</label>
                            <input wire:model.defer="total" type="number" step="0.01" class="rp-input">
                            @error('total') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Data</label>
                            <input wire:model.defer="data" type="datetime-local" class="rp-input">
                            @error('data') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Itens (JSON)</label>
                        <textarea wire:model.defer="itens_texto" rows="7" class="rp-input font-mono text-xs" placeholder='[{"nome":"Produto","quantidade":2}]'></textarea>
                        @error('itens_texto') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                    <button type="button" wire:click="closeModal" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-100"><i data-lucide="x" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>Cancelar</button>
                    <button type="button" wire:click="save" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95"><i data-lucide="save" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>Guardar</button>
                </div>
            </div>
        </div>
    @endif

    @if ($confirmDeleteOpen)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
                <h3 class="text-base font-semibold text-slate-900">Confirmar remoção</h3>
                <p class="mt-2 text-sm text-slate-600">Deseja remover esta compra?</p>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="$set('confirmDeleteOpen', false)" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-50"><i data-lucide="x" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>Fechar</button>
                    <button type="button" wire:click="delete" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-100"><i data-lucide="trash-2" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>Remover</button>
                </div>
            </div>
        </div>
    @endif
    @endcan
</div>
