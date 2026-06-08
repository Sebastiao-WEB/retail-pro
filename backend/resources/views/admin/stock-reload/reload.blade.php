<x-layouts.desktop :title="__('pages.titles.stock_reload_form')" admin-page="stock-reload-form">
<div class="mx-auto max-w-xl space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.stock_reload.modal_title') }}</p>
            <p class="text-sm text-slate-500">{{ $product->nome }}</p>
        </div>
        <a href="{{ $backUrl }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
            <i data-lucide="arrow-left" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.back') }}
        </a>
    </div>

    <form
        method="POST"
        action="{{ route('stock.reload.apply') }}"
        class="grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-5"
    >
        @csrf
        <input type="hidden" name="productId" value="{{ $product->id }}">
        <input type="hidden" name="return_search" value="{{ $search }}">

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.quantity') }}</label>
                <input name="quantity" type="number" step="0.01" value="{{ old('quantity', '1') }}" class="rp-input @error('quantity') border-red-300 @enderror">
                @error('quantity')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.unit_cost') }}</label>
                <input name="unitCost" type="number" step="0.01" value="{{ old('unitCost', $product->preco_compra) }}" class="rp-input @error('unitCost') border-red-300 @enderror">
                @error('unitCost')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.supplier') }}</label>
            <input name="supplier" type="text" value="{{ old('supplier', 'Reposição Manual') }}" class="rp-input @error('supplier') border-red-300 @enderror">
            @error('supplier')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.destination_location') }}</label>
            <select name="to_location_id" class="rp-input @error('to_location_id') border-red-300 @enderror">
                <option value="">{{ __('app.select') }}</option>
                @foreach ($locations as $location)
                    <option value="{{ $location->id }}" @selected(old('to_location_id', $defaultLocationId) === $location->id)>
                        {{ $location->code }} - {{ $location->name }} ({{ $location->type }})
                    </option>
                @endforeach
            </select>
            @error('to_location_id')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.note') }}</label>
            <textarea name="note" rows="3" class="rp-input @error('note') border-red-300 @enderror" placeholder="{{ __('pages.common.reload_note_placeholder') }}">{{ old('note') }}</textarea>
            @error('note')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-200 pt-3">
            <a href="{{ $backUrl }}" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">{{ __('app.cancel') }}</a>
            <button type="submit" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black">{{ __('pages.common.confirm_reload') }}</button>
        </div>
    </form>
</div>
</x-layouts.desktop>
