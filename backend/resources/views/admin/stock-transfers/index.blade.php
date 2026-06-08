@php
    $stockTransfersPageRoutes = [
        'index' => route('stock.transfers'),
        'available' => route('stock.transfers.available'),
        'store' => route('stock.transfers.store'),
    ];

    $stockTransfersPageProducts = $products->map(fn ($product) => [
        'id' => $product->id,
        'nome' => $product->nome,
    ])->values();

    $stockTransfersPageLocations = $locations->map(fn ($location) => [
        'id' => $location->id,
        'code' => $location->code,
        'name' => $location->name,
    ])->values();
@endphp

<x-layouts.desktop :title="__('pages.titles.stock_transfers')" admin-page="stock-transfers">
<div
    class="space-y-4"
    data-routes='@json($stockTransfersPageRoutes)'
    data-products='@json($stockTransfersPageProducts)'
    data-locations='@json($stockTransfersPageLocations)'
>
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.stock_transfers.title') }}</p>
            <p class="text-sm text-slate-500">{{ __('pages.stock_transfers.subtitle') }}</p>
        </div>
        @can('stock.transfers.manage')
            <button type="button" data-action="open-create" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                {{ __('pages.common.new_transfer') }}
            </button>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">{{ __('app.fields.date') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.from') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.to') }}</th>
                    <th class="px-3 py-2">{{ __('app.status') }}</th>
                    <th class="px-3 py-2">{{ __('pages.common.items') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.note') }}</th>
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
                        <td colspan="6" class="px-3 py-6 text-center text-slate-500">{{ __('pages.common.no_transfers') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $transfers->links() }}</div>

    @can('stock.transfers.manage')
        <div id="stock-transfer-modal" class="rp-admin-modal hidden fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4" aria-hidden="true">
            <div class="w-full max-w-2xl rounded-xl bg-white shadow-xl">
                <div class="border-b border-slate-200 px-5 py-3">
                    <h3 class="text-base font-semibold">{{ __('pages.common.new_transfer') }}</h3>
                </div>
                <form id="stock-transfer-form" class="grid grid-cols-1 gap-3 p-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.origin_location') }}</label>
                        <select name="from_location_id" id="transfer-from-location" class="rp-input">
                            <option value="">{{ __('app.select') }}</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->code }} - {{ $location->name }}</option>
                            @endforeach
                        </select>
                        <p data-field-error="from_location_id" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.destination_location_short') }}</label>
                        <select name="to_location_id" class="rp-input">
                            <option value="">{{ __('app.select') }}</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->code }} - {{ $location->name }}</option>
                            @endforeach
                        </select>
                        <p data-field-error="to_location_id" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.product') }}</label>
                        <select name="product_id" id="transfer-product" class="rp-input">
                            <option value="">{{ __('app.select') }}</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->nome }}</option>
                            @endforeach
                        </select>
                        <p data-field-error="product_id" class="mt-1 hidden text-xs text-red-600"></p>
                        <div id="transfer-stock-info" class="mt-2 hidden rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700"></div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.quantity') }}</label>
                        <input name="quantity" type="number" step="0.01" class="rp-input">
                        <p id="transfer-available-hint" class="mt-1 hidden text-xs"></p>
                        <p data-field-error="quantity" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.note') }}</label>
                        <textarea name="note" rows="3" class="rp-input"></textarea>
                    </div>
                    <div class="md:col-span-2 flex justify-end gap-2 border-t border-slate-200 pt-3">
                        <button type="button" data-modal-close="stock-transfer-modal" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">{{ __('app.cancel') }}</button>
                        <button type="submit" data-action="create-transfer" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black">{{ __('pages.common.transfer_action') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
</div>
</x-layouts.desktop>
