<x-layouts.auth :title="__('auth.two_factor.title')">
    <div class="mb-4 text-center">
        <h1 class="text-base font-semibold text-slate-800">{{ __('auth.two_factor.heading') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('auth.two_factor.description') }}</p>
    </div>

    <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-3" id="two-factor-form">
        @csrf

        <div id="panel-code">
            <label for="code" class="mb-1 block text-xs font-semibold text-slate-600">{{ __('auth.two_factor.code_label') }}</label>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" autofocus class="rp-input text-center tracking-[0.3em]" placeholder="000000" maxlength="6">
        </div>

        <div id="panel-recovery" class="hidden">
            <label for="recovery_code" class="mb-1 block text-xs font-semibold text-slate-600">{{ __('auth.two_factor.recovery_label') }}</label>
            <input id="recovery_code" name="recovery_code" type="text" autocomplete="one-off-code" class="rp-input" placeholder="{{ __('auth.two_factor.recovery_placeholder') }}">
        </div>

        <button type="submit" data-rp-ignore-loading class="w-full rounded-lg bg-[var(--gold)] px-4 py-2.5 text-sm font-semibold text-black transition hover:brightness-95">
            {{ __('auth.two_factor.submit') }}
        </button>

        <div class="flex flex-col gap-2 text-center text-xs">
            <button type="button" id="toggle-recovery" class="font-semibold text-slate-600 hover:text-slate-800">
                {{ __('auth.two_factor.use_recovery') }}
            </button>
            <button type="button" id="toggle-code" class="hidden font-semibold text-slate-600 hover:text-slate-800">
                {{ __('auth.two_factor.use_code') }}
            </button>
            <a href="{{ route('login') }}" class="text-slate-500 hover:text-slate-700">{{ __('auth.two_factor.back_to_login') }}</a>
        </div>
    </form>

    <script>
        (function () {
            const panelCode = document.getElementById('panel-code');
            const panelRecovery = document.getElementById('panel-recovery');
            const toggleRecovery = document.getElementById('toggle-recovery');
            const toggleCode = document.getElementById('toggle-code');
            const codeInput = document.getElementById('code');
            const recoveryInput = document.getElementById('recovery_code');

            toggleRecovery?.addEventListener('click', () => {
                panelCode.classList.add('hidden');
                panelRecovery.classList.remove('hidden');
                toggleRecovery.classList.add('hidden');
                toggleCode.classList.remove('hidden');
                codeInput.value = '';
                recoveryInput.focus();
            });

            toggleCode?.addEventListener('click', () => {
                panelRecovery.classList.add('hidden');
                panelCode.classList.remove('hidden');
                toggleCode.classList.add('hidden');
                toggleRecovery.classList.remove('hidden');
                recoveryInput.value = '';
                codeInput.focus();
            });
        })();
    </script>
</x-layouts.auth>
