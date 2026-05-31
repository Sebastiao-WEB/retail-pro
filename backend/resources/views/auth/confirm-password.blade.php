<x-layouts.auth :title="__('auth.confirm_password.title')">
    <div class="mb-4 text-center">
        <h1 class="text-base font-semibold text-slate-800">{{ __('auth.confirm_password.heading') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('auth.confirm_password.description') }}</p>
    </div>

    <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-3">
        @csrf
        <div>
            <label for="password" class="mb-1 block text-xs font-semibold text-slate-600">{{ __('auth.password') }}</label>
            <input id="password" name="password" type="password" required autofocus autocomplete="current-password" class="rp-input" placeholder="••••••">
        </div>

        <button type="submit" data-rp-ignore-loading class="w-full rounded-lg bg-[var(--gold)] px-4 py-2.5 text-sm font-semibold text-black transition hover:brightness-95">
            {{ __('auth.confirm_password.submit') }}
        </button>
    </form>
</x-layouts.auth>
