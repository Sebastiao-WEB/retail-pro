<div class="space-y-4">
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.stock_movements.title') }}</p>
        <p class="text-sm text-slate-500">{{ __('pages.stock_movements.subtitle') }}</p>
    </div>

    <div class="grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-4">
        <input wire:model.live.debounce.300ms="search" type="text" class="rp-input" placeholder="{{ __('pages.stock_movements.search_placeholder') }}">
        <select wire:model.live="typeFilter" class="rp-input">
            <option value="">{{ __('pages.common.all_types') }}</option>
            <option value="IN">{{ __('pages.common.movement_type_in') }}</option>
            <option value="OUT">{{ __('pages.common.movement_type_out') }}</option>
            <option value="TRANSFER">{{ __('pages.common.movement_type_transfer') }}</option>
            <option value="ADJUSTMENT">{{ __('pages.common.movement_type_adjustment') }}</option>
            <option value="RETURN">{{ __('pages.common.movement_type_return') }}</option>
        </select>
        <select wire:model.live="locationFilter" class="rp-input">
            <option value="">{{ __('pages.common.all_locations') }}</option>
            @foreach ($locations as $location)
                <option value="{{ $location->id }}">{{ $location->code }} - {{ $location->name }}</option>
            @endforeach
        </select>
        <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
            <input wire:model.live="reloadsOnly" type="checkbox" class="rounded border-slate-300">
            {{ __('pages.common.reloads_only') }}
        </label>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">{{ __('app.fields.date') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.product') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.type') }}</th>
                    <th class="px-3 py-2">{{ __('pages.common.qty_short') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.from') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.to') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.operator') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.note') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movements as $movement)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2">{{ optional($movement->created_at)->format('d/m/Y H:i') }}</td>
                        <td class="px-3 py-2 font-medium">{{ $movement->product?->nome ?? $movement->product_id }}</td>
                        <td class="px-3 py-2">
                            @if (in_array($movement->reference_type, ['PURCHASE', 'STOCK_RELOAD'], true) && $movement->type === 'IN')
                                {{ __('pages.common.movement_reload') }}
                            @elseif ($movement->type === 'ADJUSTMENT')
                                {{ __('pages.common.movement_type_adjustment') }}
                            @else
                                {{ $movement->type }}
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            @if ($movement->type === 'ADJUSTMENT' && $movement->from_location_id)
                                −{{ number_format((float) $movement->quantity, 2, ',', '.') }}
                            @elseif ($movement->type === 'ADJUSTMENT')
                                +{{ number_format((float) $movement->quantity, 2, ',', '.') }}
                            @else
                                {{ number_format((float) $movement->quantity, 2, ',', '.') }}
                            @endif
                        </td>
                        <td class="px-3 py-2">{{ $movement->fromLocation?->name ?? '---' }}</td>
                        <td class="px-3 py-2">{{ $movement->toLocation?->name ?? '---' }}</td>
                        <td class="px-3 py-2">{{ $movement->performedBy?->name ?? '---' }}</td>
                        <td class="px-3 py-2">{{ $movement->note ?: '---' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-6 text-center text-slate-500">{{ __('pages.common.no_movements') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $movements->links() }}</div>
</div>
