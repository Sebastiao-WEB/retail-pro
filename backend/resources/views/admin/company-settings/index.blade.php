@php
    $company_settings_index_blade_routes = [
'index' => route('settings.company'),
        'update' => route('settings.company.update'),
    ];
@endphp

<x-layouts.desktop :title="__('pages.titles.settings')" admin-page="company-settings">
<div
    class="space-y-4"
    data-routes='@json($company_settings_index_blade_routes)'
>
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.company_settings.title') }}</p>
        <p class="text-sm text-slate-500">{{ __('pages.company_settings.subtitle') }}</p>
    </div>

    <form id="company-settings-form" class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.company_settings.company_name') }}</label>
                <input name="nomeEmpresa" type="text" value="{{ $perfil->name }}" class="rp-input" placeholder="{{ __('pages.company_settings.placeholders.company_name') }}" @disabled(! $canManage)>
                <p data-field-error="nomeEmpresa" class="mt-1 hidden text-xs text-red-600"></p>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.company_settings.nif') }}</label>
                <input name="nif" type="text" value="{{ $perfil->nif }}" class="rp-input" placeholder="{{ __('pages.company_settings.placeholders.nif') }}" @disabled(! $canManage)>
                <p data-field-error="nif" class="mt-1 hidden text-xs text-red-600"></p>
            </div>
        </div>

        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.company_settings.email') }}</label>
                <input name="email" type="text" value="{{ $perfil->email }}" class="rp-input" placeholder="{{ __('pages.company_settings.placeholders.email') }}" @disabled(! $canManage)>
                <p data-field-error="email" class="mt-1 hidden text-xs text-red-600"></p>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.company_settings.phone') }}</label>
                <input name="telefone" type="text" value="{{ $perfil->phone }}" class="rp-input" placeholder="{{ __('pages.company_settings.placeholders.phone') }}" @disabled(! $canManage)>
                <p data-field-error="telefone" class="mt-1 hidden text-xs text-red-600"></p>
            </div>
        </div>

        <div class="mt-3">
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.company_settings.address') }}</label>
            <input name="endereco" type="text" value="{{ $perfil->address }}" class="rp-input" placeholder="{{ __('pages.company_settings.placeholders.address') }}" @disabled(! $canManage)>
            <p data-field-error="endereco" class="mt-1 hidden text-xs text-red-600"></p>
        </div>

        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.company_settings.bank') }}</label>
                <input name="banco" type="text" value="{{ $perfil->bank }}" class="rp-input" placeholder="{{ __('pages.company_settings.placeholders.bank') }}" @disabled(! $canManage)>
                <p data-field-error="banco" class="mt-1 hidden text-xs text-red-600"></p>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.company_settings.iban') }}</label>
                <input name="iban" type="text" value="{{ $perfil->iban }}" class="rp-input" placeholder="{{ __('pages.company_settings.placeholders.iban') }}" @disabled(! $canManage)>
                <p data-field-error="iban" class="mt-1 hidden text-xs text-red-600"></p>
            </div>
        </div>

        <div class="mt-3">
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.company_settings.invoice_footer') }}</label>
            <textarea name="rodapeFacturas" rows="3" class="rp-input" placeholder="{{ __('pages.company_settings.placeholders.invoice_footer') }}" @disabled(! $canManage)>{{ $perfil->invoice_footer }}</textarea>
            <p data-field-error="rodapeFacturas" class="mt-1 hidden text-xs text-red-600"></p>
        </div>

        @can('settings.manage')
            <div class="mt-4 flex justify-end border-t border-slate-100 pt-3">
                <button type="submit" data-action="save-settings" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                    <i data-lucide="save" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
                    {{ __('pages.company_settings.save') }}
                </button>
            </div>
        @else
            <p class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-500">{{ __('app.read_only') }}</p>
        @endcan
    </form>
</div>
</x-layouts.desktop>
