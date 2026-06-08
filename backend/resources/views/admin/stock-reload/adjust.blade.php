<x-layouts.desktop :title="__('pages.titles.stock_reload_adjust')" admin-page="stock-reload-adjust">
<div class="mx-auto max-w-xl space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.stock_reload.adjust_modal_title') }}</p>
            <p class="text-sm text-slate-500">{{ $product->nome }}</p>
        </div>
        <a href="{{ $backUrl }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
            <i data-lucide="arrow-left" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.back') }}
        </a>
    </div>

    <form
        id="stock-adjust-form"
        method="POST"
        action="{{ route('stock.reload.adjust') }}"
        data-balance-url="{{ $balanceUrl }}"
        data-product-id="{{ $product->id }}"
        class="grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-5"
    >
        @csrf
        <input type="hidden" name="productId" value="{{ $product->id }}">
        <input type="hidden" name="return_search" value="{{ $search }}">

        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
            {{ __('pages.stock_reload.adjust_hint') }}
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.destination_location') }}</label>
            <select name="to_location_id" id="stock-adjust-location" class="rp-input @error('to_location_id') border-red-300 @enderror">
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

        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
            <span class="text-slate-500">{{ __('pages.stock_reload.stock_at_location') }}:</span>
            <strong id="stock-adjust-balance" class="text-slate-900">{{ number_format($initialBalance, 2, ',', '.') }}</strong>
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600" for="campo-adjustmentDelta">{{ __('pages.stock_reload.adjust_delta_label') }}</label>
            <input id="campo-adjustmentDelta" name="adjustmentDelta" type="number" step="0.01" value="{{ old('adjustmentDelta') }}" class="rp-input @error('adjustmentDelta') border-red-300 @enderror" placeholder="{{ __('pages.stock_reload.adjust_delta_placeholder') }}">
            @error('adjustmentDelta')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.note') }}</label>
            <textarea name="note" rows="2" class="rp-input @error('note') border-red-300 @enderror" placeholder="{{ __('pages.common.reload_note_placeholder') }}">{{ old('note') }}</textarea>
            @error('note')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-200 pt-3">
            <a href="{{ $backUrl }}" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">{{ __('app.cancel') }}</a>
            <button type="submit" class="rounded-lg bg-amber-500 px-3 py-2 text-xs font-semibold text-black">{{ __('pages.stock_reload.confirm_adjustment') }}</button>
        </div>
    </form>
</div>
</x-layouts.desktop>
