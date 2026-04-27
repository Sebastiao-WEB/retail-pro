<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Armazéns e localizações de stock</p>
            <p class="text-sm text-slate-500">Mapeamento de locais de venda/armazenamento/danos/devoluções.</p>
        </div>
        @can('stock_locations.manage')
            <button type="button" wire:click="openCreateModal" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95"><i data-lucide="plus" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>Nova localização</button>
        @endcan
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <input wire:model.live.debounce.300ms="search" type="text" class="rp-input" placeholder="Pesquisar por código, nome ou tipo...">
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">Código</th>
                    <th class="px-3 py-2">Nome</th>
                    <th class="px-3 py-2">Tipo</th>
                    <th class="px-3 py-2">Caixa vínculo</th>
                    <th class="px-3 py-2">Vendável</th>
                    <th class="px-3 py-2">Estado</th>
                    @can('stock_locations.manage')
                        <th class="px-3 py-2">Ações</th>
                    @endcan
                </tr>
            </thead>
            <tbody>
                @forelse ($locations as $location)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 font-medium">{{ $location->code }}</td>
                        <td class="px-3 py-2">{{ $location->name }}</td>
                        <td class="px-3 py-2">{{ $location->type }}</td>
                        <td class="px-3 py-2">{{ $location->register?->name ?? '---' }}</td>
                        <td class="px-3 py-2">{{ $location->is_saleable ? 'Sim' : 'Não' }}</td>
                        <td class="px-3 py-2">
                            <span class="{{ $location->is_active ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ $location->is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                        </td>
                        @can('stock_locations.manage')
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <button type="button" wire:click="openEditModal('{{ $location->id }}')" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50"><i data-lucide="pencil" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>Editar</button>
                                    <button type="button" wire:click="confirmDelete('{{ $location->id }}')" class="rounded-md border border-red-200 px-2 py-1 text-xs text-red-600 hover:bg-red-50"><i data-lucide="power" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>Desativar</button>
                                </div>
                            </td>
                        @endcan
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ auth()->user()?->can('stock_locations.manage') ? 7 : 6 }}" class="px-3 py-6 text-center text-slate-500">Sem localizações cadastradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $locations->links() }}</div>

    @can('stock_locations.manage')
        @if ($modalOpen)
            <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4">
                <div class="w-full max-w-2xl rounded-xl bg-white shadow-xl">
                    <div class="border-b border-slate-200 px-5 py-3">
                        <h3 class="text-base font-semibold">{{ $editingId ? 'Editar localização' : 'Nova localização' }}</h3>
                    </div>
                    <div class="grid grid-cols-1 gap-3 p-5 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Código</label>
                            <input wire:model.defer="code" type="text" class="rp-input" placeholder="LOC-CX01">
                            @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Nome</label>
                            <input wire:model.defer="name" type="text" class="rp-input" placeholder="Loja - Caixa 01">
                            @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Tipo</label>
                            <select wire:model.defer="type" class="rp-input">
                                <option value="STORE_FLOOR">STORE_FLOOR</option>
                                <option value="WAREHOUSE">WAREHOUSE</option>
                                <option value="DAMAGE">DAMAGE</option>
                                <option value="RETURN_AREA">RETURN_AREA</option>
                                <option value="TRANSIT">TRANSIT</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Caixa (opcional)</label>
                            <select wire:model.defer="register_id" class="rp-input">
                                <option value="">Sem vínculo</option>
                                @foreach ($registers as $register)
                                    <option value="{{ $register->id }}">{{ $register->code }} - {{ $register->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                            <input wire:model.defer="is_saleable" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-amber-600">
                            Vendável
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                            <input wire:model.defer="is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-amber-600">
                            Ativo
                        </label>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                        <button type="button" wire:click="closeModal" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600"><i data-lucide="x" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>Cancelar</button>
                        <button type="button" wire:click="save" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black"><i data-lucide="save" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>Guardar</button>
                    </div>
                </div>
            </div>
        @endif

        @if ($confirmDeleteOpen)
            <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4">
                <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
                    <h3 class="text-base font-semibold text-slate-900">Confirmar desativação</h3>
                    <p class="mt-2 text-sm text-slate-600">Deseja desativar esta localização?</p>
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" wire:click="$set('confirmDeleteOpen', false)" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold"><i data-lucide="x" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>Fechar</button>
                        <button type="button" wire:click="delete" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600"><i data-lucide="power" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>Desativar</button>
                    </div>
                </div>
            </div>
        @endif
    @endcan
</div>
