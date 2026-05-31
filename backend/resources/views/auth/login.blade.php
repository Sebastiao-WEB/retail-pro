<x-layouts.auth>
    <form method="POST" action="{{ route('login') }}" class="space-y-3">
        @csrf
        <div>
            <label for="username" class="mb-1 block text-xs font-semibold text-slate-600">{{ __('auth.username') }}</label>
            <input id="username" name="username" type="text" required autofocus autocomplete="username" value="{{ old('username') }}" class="rp-input" placeholder="username">
        </div>

        <div>
            <label for="password" class="mb-1 block text-xs font-semibold text-slate-600">{{ __('auth.password') }}</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" class="rp-input" placeholder="••••••">
        </div>

        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
            {{ __('auth.remember') }}
        </label>

        <button type="submit" data-rp-ignore-loading class="w-full rounded-lg bg-[var(--gold)] px-4 py-2.5 text-sm font-semibold text-black transition hover:brightness-95">
            {{ __('auth.submit') }}
        </button>
    </form>
</x-layouts.auth>
