<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Transferências entre localizações</p>
            <p class="text-sm text-slate-500">Movimentação interna entre armazéns e lojas.</p>
        </div>
        @can('stock.transfers.manage')
            <button type="button" wire:click="openModal" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                Nova transferência
            </button>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">Data</th>
                    <th class="px-3 py-2">Origem</th>
                    <th class="px-3 py-2">Destino</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Itens</th>
                    <th class="px-3 py-2">Nota</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transfers as $transfer)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2">{{ optional($transfer->requested_at)->format('d/m/Y H:i') }}</td>
                        <td class="px-3 py-2">{{ $transfer->fromLocation?->code ?? $transfer->from_location_id }}</td>
                        <td class="px-3 py-2">{{ $transfer->toLocation?->code ?? $transfer->to_location_id }}</td>
                        <td class="px-3 py-2">{{ $transfer->status }}</td>
                        <td class="px-3 py-2">
                            @foreach ($transfer->items as $item)
                                <div class="text-xs text-slate-700">{{ $item->product_name_snapshot }} - {{ number_format((float) $item->quantity_requested, 2, ',', '.') }}</div>
                            @endforeach
                        </td>
                        <td class="px-3 py-2">{{ $transfer->note ?: '---' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-slate-500">Sem transferências registadas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $transfers->links() }}</div>

    @can('stock.transfers.manage')
        @if ($modalOpen)
            <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4">
                <div class="w-full max-w-2xl rounded-xl bg-white shadow-xl">
                    <div class="border-b border-slate-200 px-5 py-3">
                        <h3 class="text-base font-semibold">Nova transferência</h3>
                    </div>
                    <div class="grid grid-cols-1 gap-3 p-5 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Local origem</label>
                            <select wire:model.defer="from_location_id" class="rp-input">
                                <option value="">Selecione...</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}">{{ $location->code }} - {{ $location->name }}</option>
                                @endforeach
                            </select>
                            @error('from_location_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Local destino</label>
                            <select wire:model.defer="to_location_id" class="rp-input">
                                <option value="">Selecione...</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}">{{ $location->code }} - {{ $location->name }}</option>
                                @endforeach
                            </select>
                            @error('to_location_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Produto</label>
                            <select wire:model.defer="product_id" class="rp-input">
                                <option value="">Selecione...</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->nome }}</option>
                                @endforeach
                            </select>
                            @error('product_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Quantidade</label>
                            <input wire:model.defer="quantity" type="number" step="0.01" class="rp-input">
                            @error('quantity') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Nota</label>
                            <textarea wire:model.defer="note" rows="3" class="rp-input"></textarea>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                        <button type="button" wire:click="closeModal" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">Cancelar</button>
                        <button type="button" wire:click="createTransfer" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black">Transferir</button>
                    </div>
                </div>
            </div>
        @endif
    @endcan
</div>
