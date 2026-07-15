@php
    $ivaTipo = old('iva_tipo', $product->iva_tipo ?? 'ISENTO');
    $unidadeVenda = old('unidade_venda', $product->unidade_venda ?? 'UN');
    $isActive = old('is_active', $product->is_active);
    $controlaEstoque = old('controla_estoque', $product->controla_estoque ?? true);
@endphp

<x-layouts.desktop :title="__('pages.titles.products_edit')" admin-page="products-edit">
<div class="mx-auto max-w-2xl space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.products.edit') }}</p>
            <p class="text-sm text-slate-500">{{ $product->nome }} · {{ $product->codigo_barras ?: '—' }}</p>
        </div>
        <a href="{{ $backUrl }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
            <i data-lucide="arrow-left" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.back') }}
        </a>
    </div>

    <form
        id="product-edit-form"
        method="POST"
        action="{{ route('products.update', $product) }}"
        class="grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-5 md:grid-cols-2"
    >
        @csrf
        @method('PUT')
        <input type="hidden" name="return_search" value="{{ $search }}">

        <div class="md:col-span-2">
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.name') }}</label>
            <input name="nome" type="text" value="{{ old('nome', $product->nome) }}" class="rp-input @error('nome') border-red-300 @enderror">
            @error('nome')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600" for="campo-codigo_barras">{{ __('app.fields.barcode') }}</label>
            <input id="campo-codigo_barras" name="codigo_barras" type="text" value="{{ old('codigo_barras', $product->codigo_barras) }}" class="rp-input @error('codigo_barras') border-red-300 @enderror">
            @error('codigo_barras')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.category') }}</label>
            <input name="categoria" type="text" value="{{ old('categoria', $product->categoria) }}" class="rp-input @error('categoria') border-red-300 @enderror">
            @error('categoria')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.sale_unit') }}</label>
            <select name="unidade_venda" class="rp-input @error('unidade_venda') border-red-300 @enderror">
                <option value="UN" @selected($unidadeVenda === 'UN')>{{ __('pages.products.sale_unit_un') }}</option>
                <option value="KG" @selected($unidadeVenda === 'KG')>{{ __('pages.products.sale_unit_kg') }}</option>
            </select>
            <p class="mt-1 text-xs text-slate-500">{{ __('pages.products.sale_unit_hint') }}</p>
            @error('unidade_venda')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.purchase_price') }}</label>
            <input name="preco_compra" type="number" step="0.01" value="{{ old('preco_compra', $product->preco_compra) }}" class="rp-input @error('preco_compra') border-red-300 @enderror">
            @error('preco_compra')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.sale_price') }}</label>
            <input name="preco_venda" type="number" step="0.01" value="{{ old('preco_venda', $product->preco_venda) }}" class="rp-input @error('preco_venda') border-red-300 @enderror">
            @error('preco_venda')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.iva_type') }}</label>
            <select name="iva_tipo" id="product-iva-tipo" class="rp-input @error('iva_tipo') border-red-300 @enderror">
                <option value="ISENTO" @selected($ivaTipo === 'ISENTO')>ISENTO</option>
                <option value="PERCENTUAL" @selected($ivaTipo === 'PERCENTUAL')>PERCENTUAL</option>
                <option value="MONETARIO" @selected($ivaTipo === 'MONETARIO')>MONETARIO</option>
            </select>
            @error('iva_tipo')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div data-iva-panel="PERCENTUAL" class="{{ $ivaTipo === 'PERCENTUAL' ? '' : 'hidden' }}">
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.iva_percent') }}</label>
            <input name="iva_percentual" type="number" step="0.01" value="{{ old('iva_percentual', $product->iva_percentual) }}" class="rp-input @error('iva_percentual') border-red-300 @enderror" placeholder="16.00">
            @error('iva_percentual')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div data-iva-panel="MONETARIO" class="{{ $ivaTipo === 'MONETARIO' ? '' : 'hidden' }}">
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.iva_amount') }}</label>
            <input name="iva_valor" type="number" step="0.01" value="{{ old('iva_valor', $product->iva_valor) }}" class="rp-input @error('iva_valor') border-red-300 @enderror" placeholder="5.00">
            @error('iva_valor')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div data-iva-panel="ISENTO" class="{{ $ivaTipo === 'ISENTO' ? '' : 'hidden' }} rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600 md:col-span-2">
            {{ __('pages.products.iva_exempt_note') }}
        </div>

        <div class="md:col-span-2" data-stock-panel>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.stock') }}</label>
            @if ($controlaEstoque)
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                    <span class="font-semibold text-slate-800">{{ number_format((float) $stockExibido, 2, ',', '.') }}</span>
                    <span class="text-slate-500"> — {{ __('pages.products.stock_readonly') }}</span>
                </div>
            @else
                <div class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900">
                    {{ __('pages.products.sem_controlo_stock') }} — {{ __('pages.products.stock_not_applicable') }}
                </div>
            @endif
        </div>

        <div class="md:col-span-2">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="hidden" name="controla_estoque" value="0">
                <input name="controla_estoque" type="checkbox" value="1" @checked($controlaEstoque) class="h-4 w-4 rounded border-slate-300 text-amber-600">
                {{ __('pages.products.controla_estoque') }}
            </label>
            <p class="mt-1 text-xs text-slate-500">{{ __('pages.products.controla_estoque_hint') }}</p>
        </div>

        <div class="md:col-span-2">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="hidden" name="is_active" value="0">
                <input name="is_active" type="checkbox" value="1" @checked($isActive) class="h-4 w-4 rounded border-slate-300 text-amber-600">
                {{ __('pages.products.active_pos') }}
            </label>
            <p class="mt-1 text-xs text-slate-500">{{ __('pages.products.active_pos_hint') }}</p>
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-200 pt-3 md:col-span-2">
            <a href="{{ $backUrl }}" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">{{ __('app.cancel') }}</a>
            <button type="submit" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                <i data-lucide="save" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.save') }}
            </button>
        </div>
    </form>
</div>
</x-layouts.desktop>
