@php
    $isActive = old('is_active', $register->is_active);
    $selectedLocationIds = old('stock_location_ids', $register->stockLocations->pluck('id')->all());
@endphp

<x-layouts.desktop :title="__('pages.titles.registers_edit')" admin-page="registers-edit">
<div class="mx-auto max-w-2xl space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.registers.edit') }}</p>
            <p class="text-sm text-slate-500">{{ $register->code }} — {{ $register->name }}</p>
        </div>
        <a href="{{ $backUrl }}" data-rp-page-nav class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
            <i data-lucide="arrow-left" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.back') }}
        </a>
    </div>

    <form
        method="POST"
        action="{{ route('registers.update', $register) }}"
        class="grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-5"
    >
        @csrf
        @method('PUT')
        <input type="hidden" name="return_search" value="{{ $search }}">

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.code') }}</label>
            <input name="code" type="text" value="{{ old('code', $register->code) }}" class="rp-input @error('code') border-red-300 @enderror" placeholder="{{ __('pages.registers.code_placeholder') }}">
            @error('code')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.name') }}</label>
            <input name="name" type="text" value="{{ old('name', $register->name) }}" class="rp-input @error('name') border-red-300 @enderror" placeholder="{{ __('pages.registers.name_placeholder') }}">
            @error('name')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.registers.stock_locations') }}</label>
            <p class="mb-2 text-xs text-slate-500">{{ __('pages.registers.stock_locations_hint') }}</p>
            @if ($locations->isEmpty())
                <p class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">{{ __('pages.registers.no_stock_locations') }}</p>
            @else
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach ($locations as $location)
                        <label class="inline-flex items-start gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 {{ ! $location->is_active ? 'bg-slate-50 opacity-75' : '' }}">
                            <input
                                type="checkbox"
                                name="stock_location_ids[]"
                                value="{{ $location->id }}"
                                @checked(in_array($location->id, $selectedLocationIds, true))
                                class="mt-0.5 h-4 w-4 shrink-0 accent-amber-500"
                            >
                            <span>
                                <span class="font-medium">{{ $location->code }}</span>
                                — {{ $location->name }}
                                <span class="block text-xs text-slate-500">{{ $location->type }}{{ ! $location->is_active ? ' · '.__('app.inactive') : '' }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            @endif
            @error('stock_location_ids')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
            @error('stock_location_ids.*')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                <input type="hidden" name="is_active" value="0">
                <input name="is_active" type="checkbox" value="1" @checked($isActive) class="h-4 w-4 rounded border-slate-300 text-amber-600">
                {{ __('app.active') }}
            </label>
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-200 pt-3">
            <a href="{{ $backUrl }}" data-rp-page-nav class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">{{ __('app.cancel') }}</a>
            <button type="submit" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                <i data-lucide="save" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.save') }}
            </button>
        </div>
    </form>
</div>
</x-layouts.desktop>
