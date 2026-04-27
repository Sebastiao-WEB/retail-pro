<div class="space-y-4">
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Recarregar stock</p>
        <p class="text-sm text-slate-500">Entrada rápida de stock para reposição operacional do POS.</p>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <input wire:model.live.debounce.300ms="search" type="text" class="rp-input" placeholder="Pesquisar produto por nome, código ou categoria...">
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">Produto</th>
                    <th class="px-3 py-2">Categoria</th>
                    <th class="px-3 py-2">Stock atual</th>
                    <th class="px-3 py-2">Preço compra</th>
                    <th class="px-3 py-2">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 font-medium">{{ $product->nome }}</td>
                        <td class="px-3 py-2">{{ $product->categoria ?: '---' }}</td>
                        <td class="px-3 py-2">{{ number_format((float) $product->stock, 2, ',', '.') }}</td>
                        <td class="px-3 py-2">{{ number_format((float) $product->preco_compra, 2, ',', '.') }} MZN</td>
                        <td class="px-3 py-2">
                            @can('stock.reload')
                                <button type="button" wire:click="openReloadModal('{{ $product->id }}')" class="rounded-md border border-emerald-200 px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">
                                    Recarregar
                                </button>
                            @else
                                <span class="text-xs text-slate-400">Sem permissão</span>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-6 text-center text-slate-500">Sem produtos ativos.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $products->links() }}</div>

    @can('stock.reload')
        @if ($reloadModalOpen)
            <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4">
                <div class="w-full max-w-xl rounded-xl bg-white shadow-xl">
                    <div class="border-b border-slate-200 px-5 py-3">
                        <h3 class="text-base font-semibold">Recarregar stock</h3>
                        <p class="text-sm text-slate-500">{{ $productName }}</p>
                    </div>
                    <div class="grid grid-cols-1 gap-3 p-5">
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">Quantidade</label>
                                <input wire:model.defer="quantity" type="number" step="0.01" class="rp-input">
                                @error('quantity') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">Custo unitário</label>
                                <input wire:model.defer="unitCost" type="number" step="0.01" class="rp-input">
                                @error('unitCost') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Fornecedor</label>
                            <input wire:model.defer="supplier" type="text" class="rp-input">
                            @error('supplier') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Localização destino</label>
                            <select wire:model.defer="to_location_id" class="rp-input">
                                <option value="">Selecione...</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}">{{ $location->code }} - {{ $location->name }} ({{ $location->type }})</option>
                                @endforeach
                            </select>
                            @error('to_location_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Nota</label>
                            <textarea wire:model.defer="note" rows="3" class="rp-input" placeholder="Observação da recarga..."></textarea>
                            @error('note') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                        <button type="button" wire:click="$set('reloadModalOpen', false)" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">Cancelar</button>
                        <button type="button" wire:click="applyReload" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black">Confirmar recarga</button>
                    </div>
                </div>
            </div>
        @endif
    @endcan
</div>
