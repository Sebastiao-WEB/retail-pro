@props(['title' => null, 'adminPage' => null])

<!doctype html>
<html lang="{{ \App\Support\SupportedLocales::htmlLang(app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('app.default_title') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/admin.js'])
</head>
<body class="h-screen overflow-hidden bg-[var(--bg-app)] text-slate-900" @if(!empty($adminPage)) data-admin-page="{{ $adminPage }}" @endif>
    @if (!empty($adminPage))
        <div
            id="rp-admin-preloader"
            class="rp-admin-preloader"
            role="status"
            aria-live="polite"
            aria-label="{{ __('app.loading') }}"
        >
            <div class="rp-admin-preloader-panel">
                <img src="{{ asset('assets/images/rp.png') }}" alt="" class="rp-admin-preloader-logo" aria-hidden="true">
                <div class="rp-admin-preloader-spinner" aria-hidden="true"></div>
                <p class="rp-admin-preloader-text">{{ __('app.loading') }}</p>
            </div>
        </div>
    @endif

    @php
        $user = auth()->user();
        $nomeUtilizador = $user?->name ?? __('app.operator');
        $cargoUtilizador = $user?->getRoleNames()->first() ?? $user?->role ?? __('app.user');
        $dataAtual = now()->translatedFormat('l, d \\d\\e F \\d\\e Y');
    @endphp

    <div class="flex h-screen">
        <aside class="fixed left-0 top-0 flex h-screen w-64 flex-col bg-[var(--dark)] text-slate-100">
            <div class="border-b border-white/10 px-5 py-5">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('assets/images/rp.png') }}" alt="{{ __('app.brand') }} {{ __('app.brand_pos') }}" class="h-9 w-9 rounded-lg object-contain">
                    <div>
                        <h1 class="text-sm font-bold leading-tight">{{ __('app.brand') }} <span class="text-[var(--gold)]">{{ __('app.brand_pos') }}</span></h1>
                        <p class="text-[10px] text-slate-400">{{ __('app.backoffice') }}</p>
                    </div>
                </div>
            </div>

            <nav class="flex-1 overflow-auto px-3 py-4">
                <div class="mb-5">
                    <p class="mb-2 px-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('app.sections.pos') }}</p>
                    @can('dashboard.view')
                        <a href="{{ route('dashboard') }}"
                           class="{{ request()->routeIs('dashboard') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="layout-dashboard" class="h-4 w-4"></i>
                            <span>{{ __('app.menu.dashboard') }}</span>
                        </a>
                    @endcan
                    @can('sales.view')
                        <a href="{{ route('sales.index') }}"
                           class="{{ request()->routeIs('sales.*') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="shopping-cart" class="h-4 w-4"></i>
                            <span>{{ __('app.menu.sales') }}</span>
                        </a>
                    @endcan
                    @can('balance_sheets.view')
                        <a href="{{ route('balance-sheets.index') }}"
                           class="{{ request()->routeIs('balance-sheets.*') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="scale" class="h-4 w-4"></i>
                            <span>{{ __('app.menu.balance_sheets') }}</span>
                        </a>
                    @endcan
                    @can('operator_reports.view')
                        <a href="{{ route('operator-reports.index') }}"
                           class="{{ request()->routeIs('operator-reports.*') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="users" class="h-4 w-4"></i>
                            <span>{{ __('app.menu.operator_reports') }}</span>
                        </a>
                    @endcan
                    @can('cash_sessions.view')
                        <a href="{{ route('cash-sessions.active') }}"
                           class="{{ request()->routeIs('cash-sessions.active') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="door-open" class="h-4 w-4"></i>
                            <span>{{ __('app.menu.cash_sessions_active') }}</span>
                        </a>
                        <a href="{{ route('cash-sessions.closed') }}"
                           class="{{ request()->routeIs('cash-sessions.closed') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="history" class="h-4 w-4"></i>
                            <span>{{ __('app.menu.cash_sessions_closed') }}</span>
                        </a>
                    @endcan
                    @can('stock.reload')
                        <a href="{{ route('stock.reload.history') }}"
                           class="{{ request()->routeIs('stock.reload.history') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="history" class="h-4 w-4"></i>
                            <span>{{ __('app.menu.stock_reload_history') }}</span>
                        </a>
                    @endcan
                    @can('stock.movements.view')
                        <a href="{{ route('stock.movements') }}"
                           class="{{ request()->routeIs('stock.movements') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="arrow-right-left" class="h-4 w-4"></i>
                            <span>{{ __('app.menu.stock_movements') }}</span>
                        </a>
                    @endcan
                    @can('stock.transfers.view')
                        <a href="{{ route('stock.transfers') }}"
                           class="{{ request()->routeIs('stock.transfers') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="truck" class="h-4 w-4"></i>
                            <span>{{ __('app.menu.stock_transfers') }}</span>
                        </a>
                    @endcan
                </div>

                <div class="mb-5">
                    <p class="mb-2 px-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('app.sections.system') }}</p>
                    @can('products.view')
                        <a href="{{ route('products.index') }}"
                           class="{{ request()->routeIs(['products.*', 'stock.reload.form', 'stock.reload.adjust.form']) ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="box" class="h-4 w-4"></i>
                            <span>{{ __('app.menu.products') }}</span>
                        </a>
                    @endcan
                    @can('customers.view')
                        <a href="{{ route('customers.index') }}"
                           class="{{ request()->routeIs('customers.*') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="users" class="h-4 w-4"></i>
                            <span>{{ __('app.menu.customers') }}</span>
                        </a>
                    @endcan
                    @can('reversals.view')
                        <a href="{{ route('reversals.index') }}"
                           class="{{ request()->routeIs('reversals.*') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="undo-2" class="h-4 w-4"></i>
                            <span>{{ __('app.menu.reversals') }}</span>
                        </a>
                    @endcan
                    @can('registers.view')
                        <a href="{{ route('registers.index') }}"
                           class="{{ request()->routeIs('registers.*') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="wallet" class="h-4 w-4"></i>
                            <span>{{ __('app.menu.registers') }}</span>
                        </a>
                    @endcan
                    @can('stock_locations.view')
                        <a href="{{ route('stock-locations.index') }}"
                           class="{{ request()->routeIs('stock-locations.*') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="warehouse" class="h-4 w-4"></i>
                            <span>{{ __('app.menu.stock_locations') }}</span>
                        </a>
                    @endcan
                    @can('users.view')
                        <a href="{{ route('users.index') }}"
                           class="{{ request()->routeIs('users.*') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="user-cog" class="h-4 w-4"></i>
                            <span>{{ __('app.menu.users') }}</span>
                        </a>
                    @endcan
                    @can('roles.view')
                        <a href="{{ route('roles.permissions') }}"
                           class="{{ request()->routeIs('roles.permissions') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="shield-check" class="h-4 w-4"></i>
                            <span>{{ __('app.menu.roles_permissions') }}</span>
                        </a>
                    @endcan
                    @can('settings.view')
                        <a href="{{ route('settings.company') }}"
                           class="{{ request()->routeIs('settings.company') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="settings" class="h-4 w-4"></i>
                            <span>{{ __('app.menu.settings') }}</span>
                        </a>
                    @endcan
                    <a href="{{ route('security.settings') }}"
                       class="{{ request()->routeIs('security.settings') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                        <i data-lucide="shield" class="h-4 w-4"></i>
                        <span>{{ __('app.menu.security') }}</span>
                    </a>
                </div>
            </nav>

            <div class="border-t border-white/10 px-4 py-3 space-y-2">
                <x-language-switcher />
                <div class="rounded-lg px-2 py-2 hover:bg-[var(--dark-soft)]">
                    <div class="flex items-center gap-2">
                        <div class="flex h-7 w-7 items-center justify-center rounded-md bg-[var(--gold)] text-xs font-bold text-black">
                            {{ strtoupper(substr($nomeUtilizador, 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-semibold text-slate-200">{{ $nomeUtilizador }}</p>
                            <p class="truncate text-[10px] text-slate-400">{{ $cargoUtilizador }}</p>
                        </div>
                    </div>
                    <div class="mt-2 rounded-md border border-white/10 bg-black/40 px-2 py-1 text-center text-[11px] font-bold tracking-wide text-cyan-300">
                        {{ now()->format('H:i:s') }}
                    </div>
                </div>
            </div>
        </aside>

        <main class="ml-64 flex h-screen flex-1 flex-col overflow-hidden">
            <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center justify-between border-b border-[var(--border)] bg-white px-6 py-3.5">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">{{ $title ?? __('app.default_title') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('app.central_management') }} · {{ $dataAtual }}</p>
                </div>

                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <p class="text-xs font-semibold text-slate-800">{{ $nomeUtilizador }}</p>
                        <p class="text-[11px] text-slate-500">{{ $cargoUtilizador }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100">
                            <i data-lucide="log-out" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
                            {{ __('app.logout') }}
                        </button>
                    </form>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-6">
                <div class="rp-card min-h-full p-6">
                    {{ $slot }}
                </div>
            </div>
        </main>
    </div>

    @php
        $toast = session('toast');
        $toastMessage = is_array($toast) ? ($toast['message'] ?? null) : null;
        $toastType = is_array($toast) ? ($toast['type'] ?? 'info') : 'info';
        $errorMessage = $errors->any() ? $errors->first() : null;
    @endphp

    <script>
        window.addEventListener('load', () => {
            if (typeof window.retailToast !== 'function') return;

            @if ($errorMessage)
                window.retailToast(@js($errorMessage), 'error');
            @elseif ($toastMessage)
                window.retailToast(@js($toastMessage), @js($toastType));
            @endif
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @stack('scripts')
</body>
</html>
