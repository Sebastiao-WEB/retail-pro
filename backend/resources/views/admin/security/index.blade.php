@php
    $security_index_blade_routes = [
        'index' => route('security.settings'),
        'qrCode' => route('two-factor.qr-code'),
        'recoveryCodes' => route('two-factor.recovery-codes'),
    ];
@endphp

<x-layouts.desktop :title="__('auth.security.title') . ' | RetailPro'" admin-page="security">
<div
    class="space-y-4"
    data-routes='@json($security_index_blade_routes)'
    data-qr-failed="{{ __('auth.security.qr_failed') }}"
    data-recovery-failed="{{ __('auth.security.recovery_failed') }}"
    data-disable-confirm="{{ __('auth.security.disable_confirm') }}"
>
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('auth.security.title') }}</p>
        <p class="text-sm text-slate-500">{{ __('auth.security.subtitle') }}</p>
    </div>

    @php
        $fortifyStatus = session('status');
        $statusKey = $fortifyStatus ? 'auth.fortify_status.'.$fortifyStatus : null;
        $statusMessage = $statusKey && __($statusKey) !== $statusKey ? __($statusKey) : null;
    @endphp

    @if ($statusMessage)
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ $statusMessage }}
        </div>
    @endif

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-800">{{ __('auth.security.two_factor_heading') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('auth.security.two_factor_description') }}</p>
            </div>
            @if ($twoFactorConfirmed)
                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">{{ __('auth.security.enabled') }}</span>
            @elseif ($twoFactorPending)
                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">{{ __('auth.security.pending') }}</span>
            @else
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ __('auth.security.disabled') }}</span>
            @endif
        </div>

        @if ($twoFactorPending)
            <div class="mt-4 space-y-4 border-t border-slate-100 pt-4">
                <p class="text-sm text-slate-600">{{ __('auth.security.scan_qr') }}</p>
                <div id="two-factor-qr" class="flex justify-center rounded-lg border border-slate-100 bg-slate-50 p-4"></div>

                <form method="POST" action="{{ route('two-factor.confirm') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label for="confirm-code" class="mb-1 block text-xs font-semibold text-slate-600">{{ __('auth.security.confirm_code_label') }}</label>
                        <input id="confirm-code" name="code" type="text" inputmode="numeric" required maxlength="6" class="rp-input max-w-xs" placeholder="000000">
                    </div>
                    <button type="submit" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                        {{ __('auth.security.confirm_enable') }}
                    </button>
                </form>
            </div>
        @elseif ($twoFactorConfirmed)
            <div class="mt-4 space-y-4 border-t border-slate-100 pt-4">
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('auth.security.recovery_codes') }}</p>
                    <p class="text-sm text-slate-500">{{ __('auth.security.recovery_codes_hint') }}</p>
                    <ul id="recovery-codes-list" class="mt-3 grid gap-2 sm:grid-cols-2"></ul>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" id="load-recovery-codes" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            {{ __('auth.security.show_recovery_codes') }}
                        </button>
                        <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                {{ __('auth.security.regenerate_recovery_codes') }}
                            </button>
                        </form>
                    </div>
                </div>

                <form method="POST" action="{{ route('two-factor.disable') }}" data-security-disable data-confirm-message="{{ __('auth.security.disable_confirm') }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100">
                        {{ __('auth.security.disable') }}
                    </button>
                </form>
            </div>
        @else
            <div class="mt-4 border-t border-slate-100 pt-4">
                <form method="POST" action="{{ route('two-factor.enable') }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                        {{ __('auth.security.enable') }}
                    </button>
                </form>
                <p class="mt-2 text-xs text-slate-500">{{ __('auth.security.enable_hint') }}</p>
            </div>
        @endif
    </div>
</div>
</x-layouts.desktop>
