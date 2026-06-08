<x-layouts.desktop :title="__('pages.titles.stock_reload')" admin-page="stock-reload">
<div class="space-y-4">
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
                                    <a
                                        href="{{ route('stock.reload.form', ['product' => $product, 'search' => $search]) }}"
                                        class="rounded-md border border-emerald-200 px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
                                    >
                                        {{ __('pages.common.reload_action') }}
                                    </a>
                                    <a
                                        href="{{ route('stock.reload.adjust.form', ['product' => $product, 'search' => $search]) }}"
                                        class="rounded-md border border-amber-200 px-2 py-1 text-xs font-semibold text-amber-800 hover:bg-amber-50"
                                    >
                                        {{ __('pages.stock_reload.adjust_action') }}
                                    </a>
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
</div>
</x-layouts.desktop>
