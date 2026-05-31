<div class="space-y-4">
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.company_settings.title') }}</p>
        <p class="text-sm text-slate-500">{{ __('pages.company_settings.subtitle') }}</p>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.company_settings.company_name') }}</label>
                <input wire:model.defer="nomeEmpresa" type="text" class="rp-input" placeholder="{{ __('pages.company_settings.placeholders.company_name') }}" @disabled(! auth()->user()?->can('settings.manage'))>
                @error('nomeEmpresa') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.company_settings.nif') }}</label>
                <input wire:model.defer="nif" type="text" class="rp-input" placeholder="{{ __('pages.company_settings.placeholders.nif') }}" @disabled(! auth()->user()?->can('settings.manage'))>
                @error('nif') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.company_settings.email') }}</label>
                <input wire:model.defer="email" type="text" class="rp-input" placeholder="{{ __('pages.company_settings.placeholders.email') }}" @disabled(! auth()->user()?->can('settings.manage'))>
                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.company_settings.phone') }}</label>
                <input wire:model.defer="telefone" type="text" class="rp-input" placeholder="{{ __('pages.company_settings.placeholders.phone') }}" @disabled(! auth()->user()?->can('settings.manage'))>
                @error('telefone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-3">
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.company_settings.address') }}</label>
            <input wire:model.defer="endereco" type="text" class="rp-input" placeholder="{{ __('pages.company_settings.placeholders.address') }}" @disabled(! auth()->user()?->can('settings.manage'))>
            @error('endereco') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.company_settings.bank') }}</label>
                <input wire:model.defer="banco" type="text" class="rp-input" placeholder="{{ __('pages.company_settings.placeholders.bank') }}" @disabled(! auth()->user()?->can('settings.manage'))>
                @error('banco') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.company_settings.iban') }}</label>
                <input wire:model.defer="iban" type="text" class="rp-input" placeholder="{{ __('pages.company_settings.placeholders.iban') }}" @disabled(! auth()->user()?->can('settings.manage'))>
                @error('iban') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-3">
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.company_settings.invoice_footer') }}</label>
            <textarea wire:model.defer="rodapeFacturas" rows="3" class="rp-input" placeholder="{{ __('pages.company_settings.placeholders.invoice_footer') }}" @disabled(! auth()->user()?->can('settings.manage'))></textarea>
            @error('rodapeFacturas') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        @can('settings.manage')
            <div class="mt-4 flex justify-end border-t border-slate-100 pt-3">
                <button type="button" wire:click="salvar" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                    <i data-lucide="save" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
                    {{ __('pages.company_settings.save') }}
                </button>
            </div>
        @else
            <p class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-500">{{ __('app.read_only') }}</p>
        @endcan
    </div>
</div>
