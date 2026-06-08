@php
    $stock_reload_index_blade_routes = [
'index' => route('stock.reload'),
        'reload' => route('stock.reload.apply'),
        'adjust' => route('stock.reload.adjust'),
        'balance' => route('stock.reload.balance'),
    ];
@endphp

<x-layouts.desktop :title="__('pages.titles.stock_reload')" admin-page="stock-reload">
<div
    class="space-y-4"
    data-routes='@json($stock_reload_index_blade_routes)'
    data-default-location-id="@js($defaultLocationId)"
>
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.stock_reload.title') }}</p>
        <p class="text-sm text-slate-500">{{ __('pages.stock_reload.subtitle') }}</p>
    </div>

    <form method="GET" action="{{ route('stock.reload') }}" data-auto-submit data-debounce="300" class="rounded-lg border border-slate-200 bg-white p-4">
        <input name="search" type="text" value="{{ $search }}" class="rp-input" placeholder="{{ __('pages.stock_reload.search_placeholder') }}">
    </form>

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
                                <div class="flex flex-wrap gap-1">
                                    <button type="button" data-action="open-reload" data-id="{{ $product->id }}" data-name="{{ $product->nome }}" data-cost="{{ $product->preco_compra }}" class="rounded-md border border-emerald-200 px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">
                                        {{ __('pages.common.reload_action') }}
                                    </button>
                                    <button type="button" data-action="open-adjust" data-id="{{ $product->id }}" data-name="{{ $product->nome }}" class="rounded-md border border-amber-200 px-2 py-1 text-xs font-semibold text-amber-800 hover:bg-amber-50">
                                        {{ __('pages.stock_reload.adjust_action') }}
                                    </button>
                                </div>
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
        <div id="stock-reload-modal" class="rp-admin-modal hidden fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4" aria-hidden="true">
            <div class="w-full max-w-xl rounded-xl bg-white shadow-xl">
                <div class="border-b border-slate-200 px-5 py-3">
                    <h3 class="text-base font-semibold">{{ __('pages.stock_reload.modal_title') }}</h3>
                    <p id="stock-reload-product-name" class="text-sm text-slate-500"></p>
                </div>
                <form id="stock-reload-form" class="grid grid-cols-1 gap-3 p-5">
                    <input type="hidden" name="productId" id="stock-reload-product-id" value="">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.quantity') }}</label>
                            <input name="quantity" type="number" step="0.01" class="rp-input">
                            <p data-field-error="quantity" class="mt-1 hidden text-xs text-red-600"></p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.unit_cost') }}</label>
                            <input name="unitCost" type="number" step="0.01" class="rp-input">
                            <p data-field-error="unitCost" class="mt-1 hidden text-xs text-red-600"></p>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.supplier') }}</label>
                        <input name="supplier" type="text" class="rp-input">
                        <p data-field-error="supplier" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.destination_location') }}</label>
                        <select name="to_location_id" id="stock-reload-location" class="rp-input">
                            <option value="">{{ __('app.select') }}</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected($defaultLocationId === $location->id)>{{ $location->code }} - {{ $location->name }} ({{ $location->type }})</option>
                            @endforeach
                        </select>
                        <p data-field-error="to_location_id" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.note') }}</label>
                        <textarea name="note" rows="3" class="rp-input" placeholder="{{ __('pages.common.reload_note_placeholder') }}"></textarea>
                        <p data-field-error="note" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-200 pt-3">
                        <button type="button" data-modal-close="stock-reload-modal" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">{{ __('app.cancel') }}</button>
                        <button type="submit" data-action="apply-reload" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black">{{ __('pages.common.confirm_reload') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="stock-adjust-modal" class="rp-admin-modal hidden fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4" aria-hidden="true">
            <div class="w-full max-w-xl rounded-xl bg-white shadow-xl">
                <div class="border-b border-slate-200 px-5 py-3">
                    <h3 class="text-base font-semibold">{{ __('pages.stock_reload.adjust_modal_title') }}</h3>
                    <p id="stock-adjust-product-name" class="text-sm text-slate-500"></p>
                </div>
                <form id="stock-adjust-form" class="grid grid-cols-1 gap-3 p-5">
                    <input type="hidden" name="productId" id="stock-adjust-product-id" value="">
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                        {{ __('pages.stock_reload.adjust_hint') }}
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.destination_location') }}</label>
                        <select name="to_location_id" id="stock-adjust-location" class="rp-input">
                            <option value="">{{ __('app.select') }}</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected($defaultLocationId === $location->id)>{{ $location->code }} - {{ $location->name }} ({{ $location->type }})</option>
                            @endforeach
                        </select>
                        <p data-field-error="to_location_id" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                        <span class="text-slate-500">{{ __('pages.stock_reload.stock_at_location') }}:</span>
                        <strong id="stock-adjust-balance" class="text-slate-900">0,00</strong>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600" for="campo-adjustmentDelta">{{ __('pages.stock_reload.adjust_delta_label') }}</label>
                        <input id="campo-adjustmentDelta" name="adjustmentDelta" type="number" step="0.01" class="rp-input" placeholder="{{ __('pages.stock_reload.adjust_delta_placeholder') }}">
                        <p data-field-error="adjustmentDelta" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.note') }}</label>
                        <textarea name="note" rows="2" class="rp-input" placeholder="{{ __('pages.common.reload_note_placeholder') }}"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-200 pt-3">
                        <button type="button" data-modal-close="stock-adjust-modal" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">{{ __('app.cancel') }}</button>
                        <button type="submit" data-action="apply-adjustment" class="rounded-lg bg-amber-500 px-3 py-2 text-xs font-semibold text-black">{{ __('pages.stock_reload.confirm_adjustment') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
</div>
</x-layouts.desktop>
