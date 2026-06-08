<x-layouts.desktop :title="__('pages.titles.stock_reload_history')" admin-page="stock-reload-history">
<div class="space-y-4">
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.stock_reload_history.title') }}</p>
        <p class="text-sm text-slate-500">{{ __('pages.stock_reload_history.subtitle') }}</p>
    </div>

    <form method="GET" action="{{ route('stock.reload.history') }}" data-auto-submit data-debounce="300" class="rounded-lg border border-slate-200 bg-white p-4">
        <input name="search" type="text" value="{{ $search }}" class="rp-input" placeholder="{{ __('pages.stock_reload_history.search_placeholder') }}">
    </form>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
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
</div>
</x-layouts.desktop>
