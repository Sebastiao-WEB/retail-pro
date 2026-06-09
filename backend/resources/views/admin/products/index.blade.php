@php
    $products_index_blade_routes = [
        'index' => route('products.index'),
        'store' => route('products.store'),
    ];
    $showActions = $canManage || $canReload;
    $tableColspan = $showActions ? 6 : 5;
@endphp

<x-layouts.desktop :title="__('pages.titles.products')" admin-page="products">
<div
    class="space-y-4"
    data-routes='@json($products_index_blade_routes)'
>
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.products.title') }}</p>
            <p class="text-sm text-slate-500">{{ __('pages.products.subtitle') }}</p>
        </div>
        @can('products.manage')
            <button type="button" data-action="open-create" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                <i data-lucide="plus" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
                {{ __('pages.products.new') }}
            </button>
        @endcan
    </div>

    <form method="GET" action="{{ route('products.index') }}" data-auto-submit data-debounce="300" class="rounded-lg border border-slate-200 bg-white p-4">
        <input name="search" type="text" value="{{ $search }}" class="rp-input" placeholder="{{ __('pages.products.search_placeholder') }}">
    </form>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">{{ __('app.fields.name') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.code') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.sale_price') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.stock') }}</th>
                    <th class="px-3 py-2">{{ __('app.status') }}</th>
                    @if ($showActions)
                        <th class="px-3 py-2">{{ __('app.actions') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($produtos as $produto)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 font-medium">{{ $produto->nome }}</td>
                        <td class="px-3 py-2">{{ $produto->codigo_barras ?: '---' }}</td>
                        <td class="px-3 py-2">
                            {{ number_format((float) $produto->preco_venda, 2, ',', '.') }} {{ __('app.currency') }}
                            @if ($produto->unidade_venda === 'KG')
                                <span class="text-xs text-slate-500">/ kg</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">{{ number_format(\App\Support\ProductStockDisplay::stockParaExibicao($produto), 2, ',', '.') }}</td>
                        <td class="px-3 py-2">
                            <span class="{{ $produto->is_active ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ $produto->is_active ? __('app.active') : __('app.inactive') }}
                            </span>
                        </td>
                        @if ($showActions)
                            <td class="px-3 py-2">
                                <div class="flex flex-wrap items-center gap-1">
                                    @can('stock.reload')
                                        @if ($produto->is_active)
                                            <a
                                                href="{{ route('stock.reload.form', ['product' => $produto, 'return_to' => 'products', 'search' => $search]) }}"
                                                title="{{ __('pages.common.reload_action') }}"
                                                aria-label="{{ __('pages.common.reload_action') }}: {{ $produto->nome }}"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-emerald-200 text-emerald-700 hover:bg-emerald-50"
                                            >
                                                <i data-lucide="package-plus" class="h-3.5 w-3.5"></i>
                                            </a>
                                            <a
                                                href="{{ route('stock.reload.adjust.form', ['product' => $produto, 'return_to' => 'products', 'search' => $search]) }}"
                                                title="{{ __('pages.stock_reload.adjust_action') }}"
                                                aria-label="{{ __('pages.stock_reload.adjust_action') }}: {{ $produto->nome }}"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-amber-200 text-amber-800 hover:bg-amber-50"
                                            >
                                                <i data-lucide="sliders-horizontal" class="h-3.5 w-3.5"></i>
                                            </a>
                                        @endif
                                    @endcan
                                    @can('products.manage')
                                        <a href="{{ route('products.edit', ['product' => $produto, 'search' => $search]) }}" title="{{ __('app.edit') }}" aria-label="{{ __('app.edit') }}: {{ $produto->nome }}" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 text-slate-700 hover:bg-slate-50">
                                            <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                                        </a>
                                    @endcan
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $tableColspan }}" class="px-3 py-6 text-center text-slate-500">{{ __('pages.products.no_products') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $produtos->links() }}</div>

    @can('products.manage')
        <div id="product-form-modal" class="rp-admin-modal hidden fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4" aria-hidden="true">
            <div class="w-full max-w-2xl rounded-xl bg-white shadow-xl">
                <div class="border-b border-slate-200 px-5 py-3">
                    <h3 id="product-form-title" class="text-base font-semibold text-slate-900">{{ __('pages.products.new') }}</h3>
                </div>
                <form id="product-form" class="grid grid-cols-1 gap-3 p-5 md:grid-cols-2">
                    <input type="hidden" name="editing_id" id="product-editing-id" value="">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.name') }}</label>
                        <input id="campo-nome" name="nome" type="text" class="rp-input">
                        <p data-field-error="nome" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600" for="campo-codigo_barras">{{ __('app.fields.barcode') }}</label>
                        <input id="campo-codigo_barras" name="codigo_barras" type="text" class="rp-input">
                        <p data-field-error="codigo_barras" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.category') }}</label>
                        <input name="categoria" type="text" class="rp-input">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.sale_unit') }}</label>
                        <select name="unidade_venda" id="product-unidade-venda" class="rp-input">
                            <option value="UN">{{ __('pages.products.sale_unit_un') }}</option>
                            <option value="KG">{{ __('pages.products.sale_unit_kg') }}</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">{{ __('pages.products.sale_unit_hint') }}</p>
                        <p data-field-error="unidade_venda" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.purchase_price') }}</label>
                        <input name="preco_compra" type="number" step="0.01" class="rp-input">
                        <p data-field-error="preco_compra" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label id="product-preco-venda-label" class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.sale_price') }}</label>
                        <input name="preco_venda" type="number" step="0.01" class="rp-input">
                        <p data-field-error="preco_venda" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.iva_type') }}</label>
                        <select name="iva_tipo" id="product-iva-tipo" class="rp-input">
                            <option value="ISENTO">ISENTO</option>
                            <option value="PERCENTUAL">PERCENTUAL</option>
                            <option value="MONETARIO">MONETARIO</option>
                        </select>
                        <p data-field-error="iva_tipo" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div data-iva-panel="PERCENTUAL" class="hidden">
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.iva_percent') }}</label>
                        <input name="iva_percentual" type="number" step="0.01" class="rp-input" placeholder="16.00">
                        <p data-field-error="iva_percentual" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div data-iva-panel="MONETARIO" class="hidden">
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.iva_amount') }}</label>
                        <input name="iva_valor" type="number" step="0.01" class="rp-input" placeholder="5.00">
                        <p data-field-error="iva_valor" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div data-iva-panel="ISENTO" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                        {{ __('pages.products.iva_exempt_note') }}
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.stock') }}</label>
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                            {{ __('pages.products.stock_initial') }}
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input id="produto-ativo" name="is_active" type="checkbox" value="1" checked class="h-4 w-4 rounded border-slate-300 text-amber-600">
                            {{ __('pages.products.active_pos') }}
                        </label>
                        <p class="mt-1 text-xs text-slate-500">{{ __('pages.products.active_pos_hint') }}</p>
                    </div>
                    <div class="md:col-span-2 flex justify-end gap-2 border-t border-slate-200 pt-3">
                        <button type="button" data-modal-close="product-form-modal" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-100">
                            <i data-lucide="x" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.cancel') }}
                        </button>
                        <button type="submit" data-action="save-product" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                            <i data-lucide="save" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
</div>
</x-layouts.desktop>
