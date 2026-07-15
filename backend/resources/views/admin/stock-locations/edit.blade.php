@php
    $isSaleable = old('is_saleable', $location->is_saleable);
    $isActive = old('is_active', $location->is_active);
    $selectedRegisterIds = old('register_ids', $location->registers->pluck('id')->all());
@endphp

<x-layouts.desktop :title="__('pages.titles.stock_locations_edit')" admin-page="stock-locations-edit">
<div class="mx-auto max-w-2xl space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.stock_locations.edit') }}</p>
            <p class="text-sm text-slate-500">{{ $location->code }} — {{ $location->name }}</p>
        </div>
        <a href="{{ $backUrl }}" data-rp-page-nav class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
            <i data-lucide="arrow-left" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.back') }}
        </a>
    </div>

    <form
        method="POST"
        action="{{ route('stock-locations.update', $location) }}"
        class="grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-5 md:grid-cols-2"
    >
        @csrf
        @method('PUT')
        <input type="hidden" name="return_search" value="{{ $search }}">

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.code') }}</label>
            <input name="code" type="text" value="{{ old('code', $location->code) }}" class="rp-input @error('code') border-red-300 @enderror" placeholder="{{ __('pages.stock_locations.code_placeholder') }}">
            @error('code')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.name') }}</label>
            <input name="name" type="text" value="{{ old('name', $location->name) }}" class="rp-input @error('name') border-red-300 @enderror" placeholder="{{ __('pages.stock_locations.name_placeholder') }}">
            @error('name')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.type') }}</label>
            <select name="type" class="rp-input @error('type') border-red-300 @enderror">
                @foreach (['STORE_FLOOR', 'WAREHOUSE', 'DAMAGE', 'RETURN_AREA', 'TRANSIT'] as $type)
                    <option value="{{ $type }}" @selected(old('type', $location->type) === $type)>{{ $type }}</option>
                @endforeach
            </select>
            @error('type')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="md:col-span-2">
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.registers') }}</label>
            <p class="mb-2 text-xs text-slate-500">{{ __('pages.stock_locations.registers_hint') }}</p>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                @foreach ($registers as $register)
                    <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            name="register_ids[]"
                            value="{{ $register->id }}"
                            @checked(in_array($register->id, $selectedRegisterIds, true))
                            class="h-4 w-4 accent-amber-500"
                        >
                        <span>{{ $register->code }} — {{ $register->name }}</span>
                    </label>
                @endforeach
            </div>
            @error('register_ids')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
            @error('register_ids.*')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="md:col-span-2 flex flex-wrap gap-4">
            <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                <input type="hidden" name="is_saleable" value="0">
                <input name="is_saleable" type="checkbox" value="1" @checked($isSaleable) class="h-4 w-4 rounded border-slate-300 text-amber-600">
                {{ __('pages.common.saleable') }}
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                <input type="hidden" name="is_active" value="0">
                <input name="is_active" type="checkbox" value="1" @checked($isActive) class="h-4 w-4 rounded border-slate-300 text-amber-600">
                {{ __('app.active') }}
            </label>
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-200 pt-3 md:col-span-2">
            <a href="{{ $backUrl }}" data-rp-page-nav class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">{{ __('app.cancel') }}</a>
            <button type="submit" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                <i data-lucide="save" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.save') }}
            </button>
        </div>
    </form>
</div>
</x-layouts.desktop>
