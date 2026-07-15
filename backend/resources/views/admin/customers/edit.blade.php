@php
    $isActive = old('is_active', $customer->is_active);
@endphp

<x-layouts.desktop :title="__('pages.titles.customers_edit')" admin-page="customers-edit">
<div class="mx-auto max-w-2xl space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.customers.edit') }}</p>
            <p class="text-sm text-slate-500">{{ $customer->nome }}</p>
        </div>
        <a href="{{ $backUrl }}" data-rp-page-nav class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
            <i data-lucide="arrow-left" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.back') }}
        </a>
    </div>

    <form
        method="POST"
        action="{{ route('customers.update', $customer) }}"
        class="grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-5 md:grid-cols-2"
    >
        @csrf
        @method('PUT')
        <input type="hidden" name="return_search" value="{{ $search }}">

        <div class="md:col-span-2">
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.name') }}</label>
            <input name="nome" type="text" value="{{ old('nome', $customer->nome) }}" class="rp-input @error('nome') border-red-300 @enderror">
            @error('nome')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.phone') }}</label>
            <input name="telefone" type="text" value="{{ old('telefone', $customer->telefone) }}" class="rp-input @error('telefone') border-red-300 @enderror">
            @error('telefone')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.email') }}</label>
            <input name="email" type="email" value="{{ old('email', $customer->email) }}" class="rp-input @error('email') border-red-300 @enderror">
            @error('email')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.nuit') }}</label>
            <input name="nuit" type="text" value="{{ old('nuit', $customer->nuit) }}" class="rp-input @error('nuit') border-red-300 @enderror">
            @error('nuit')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="md:col-span-2">
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
