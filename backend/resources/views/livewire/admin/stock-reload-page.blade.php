<div class="space-y-4">
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.stock_reload.title') }}</p>
        <p class="text-sm text-slate-500">{{ __('pages.stock_reload.subtitle') }}</p>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <input wire:model.live.debounce.300ms="search" type="text" class="rp-input" placeholder="{{ __('pages.stock_reload.search_placeholder') }}">
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">{{ __('app.fields.product') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.category') }}</th>
                    <th class="px-3 py-2">{{ __('pages.common.current_stock') }}</th>
                    <th class="px-3 py-2">{{ __('pages.common.purchase_price') }}</th>
                    <th class="px-3 py-2">{{ __('app.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 font-medium">{{ $product->nome }}</td>
                        <td class="px-3 py-2">{{ $product->categoria ?: '---' }}</td>
                        <td class="px-3 py-2">{{ number_format((float) $product->stock, 2, ',', '.') }}</td>
                        <td class="px-3 py-2">{{ number_format((float) $product->preco_compra, 2, ',', '.') }} {{ __('app.currency') }}</td>
                        <td class="px-3 py-2">
                            @can('stock.reload')
                                <button type="button" wire:click="openReloadModal('{{ $product->id }}')" class="rounded-md border border-emerald-200 px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">
                                    {{ __('pages.common.reload_action') }}
                                </button>
                            @else
                                <span class="text-xs text-slate-400">{{ __('pages.common.no_permission') }}</span>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-6 text-center text-slate-500">{{ __('pages.common.no_active_products') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $products->links() }}</div>

    <section class="space-y-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.common.reload_history') }}</p>
            <p class="text-sm text-slate-500">{{ __('pages.common.reload_history_hint') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-2">{{ __('app.fields.date') }}</th>
                        <th class="px-3 py-2">{{ __('app.fields.product') }}</th>
                        <th class="px-3 py-2">{{ __('pages.common.qty_short') }}</th>
                        <th class="px-3 py-2">{{ __('pages.common.unit_cost') }}</th>
                        <th class="px-3 py-2">{{ __('app.fields.total') }}</th>
                        <th class="px-3 py-2">{{ __('pages.common.supplier') }}</th>
                        <th class="px-3 py-2">{{ __('app.fields.location') }}</th>
                        <th class="px-3 py-2">{{ __('app.fields.operator') }}</th>
                        <th class="px-3 py-2">{{ __('app.fields.note') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reloadHistory as $reload)
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2">{{ optional($reload->created_at)->format('d/m/Y H:i') }}</td>
                            <td class="px-3 py-2 font-medium">{{ $reload->product?->nome ?? '---' }}</td>
                            <td class="px-3 py-2">{{ number_format((float) $reload->quantity, 2, ',', '.') }}</td>
                            <td class="px-3 py-2">{{ number_format((float) $reload->unit_cost, 2, ',', '.') }} {{ __('app.currency') }}</td>
                            <td class="px-3 py-2">{{ number_format((float) $reload->quantity * (float) $reload->unit_cost, 2, ',', '.') }} {{ __('app.currency') }}</td>
                            <td class="px-3 py-2">{{ $reload->reloadRecord?->fornecedor ?? '---' }}</td>
                            <td class="px-3 py-2">{{ $reload->toLocation?->name ?? '---' }}</td>
                            <td class="px-3 py-2">{{ $reload->performedBy?->name ?? '---' }}</td>
                            <td class="px-3 py-2">{{ $reload->note ?: '---' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-3 py-6 text-center text-slate-500">{{ __('pages.common.no_reloads') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $reloadHistory->links() }}</div>
    </section>

    @can('stock.reload')
        @if ($reloadModalOpen)
            <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4">
                <div class="w-full max-w-xl rounded-xl bg-white shadow-xl">
                    <div class="border-b border-slate-200 px-5 py-3">
                        <h3 class="text-base font-semibold">{{ __('pages.stock_reload.modal_title') }}</h3>
                        <p class="text-sm text-slate-500">{{ $productName }}</p>
                    </div>
                    <div class="grid grid-cols-1 gap-3 p-5">
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.quantity') }}</label>
                                <input wire:model.defer="quantity" type="number" step="0.01" class="rp-input">
                                @error('quantity') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.unit_cost') }}</label>
                                <input wire:model.defer="unitCost" type="number" step="0.01" class="rp-input">
                                @error('unitCost') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.supplier') }}</label>
                            <input wire:model.defer="supplier" type="text" class="rp-input">
                            @error('supplier') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.destination_location') }}</label>
                            <select wire:model.defer="to_location_id" class="rp-input">
                                <option value="">{{ __('app.select') }}</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}">{{ $location->code }} - {{ $location->name }} ({{ $location->type }})</option>
                                @endforeach
                            </select>
                            @error('to_location_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.note') }}</label>
                            <textarea wire:model.defer="note" rows="3" class="rp-input" placeholder="{{ __('pages.common.reload_note_placeholder') }}"></textarea>
                            @error('note') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                        <button type="button" wire:click="$set('reloadModalOpen', false)" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">{{ __('app.cancel') }}</button>
                        <button type="button" wire:click="applyReload" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black">{{ __('pages.common.confirm_reload') }}</button>
                    </div>
                </div>
            </div>
        @endif
    @endcan
</div>
