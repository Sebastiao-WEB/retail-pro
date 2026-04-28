<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'RetailPro Backoffice' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-screen overflow-hidden bg-[var(--bg-app)] text-slate-900">
    @php
        $user = auth()->user();
        $nomeUtilizador = $user?->name ?? 'Operador';
        $cargoUtilizador = $user?->getRoleNames()->first() ?? $user?->role ?? 'Utilizador';
        $dataAtual = now()->locale('pt_PT')->translatedFormat('l, d \\d\\e F \\d\\e Y');
    @endphp

    <div class="flex h-screen">
        <aside class="fixed left-0 top-0 flex h-screen w-64 flex-col bg-[var(--dark)] text-slate-100">
            <div class="border-b border-white/10 px-5 py-5">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('assets/images/rp.png') }}" alt="RetailPro POS" class="h-9 w-9 rounded-lg object-contain">
                    <div>
                        <h1 class="text-sm font-bold leading-tight">RetailPro <span class="text-[var(--gold)]">POS</span></h1>
                        <p class="text-[10px] text-slate-400">Backoffice Livewire</p>
                    </div>
                </div>
            </div>

            <nav class="flex-1 overflow-auto px-3 py-4">
                <div class="mb-5">
                    <p class="mb-2 px-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">POS</p>
                    <a href="{{ route('dashboard') }}"
                       class="{{ request()->routeIs('dashboard') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                        <i data-lucide="layout-dashboard" class="h-4 w-4"></i>
                        <span>Painel administrativo</span>
                    </a>
                    @can('sales.view')
                        <a href="{{ route('sales.index') }}"
                           class="{{ request()->routeIs('sales.*') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="shopping-cart" class="h-4 w-4"></i>
                            <span>Vendas</span>
                        </a>
                    @endcan
                    @can('purchases.view')
                        <a href="{{ route('purchases.index') }}"
                           class="{{ request()->routeIs('purchases.*') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="package-plus" class="h-4 w-4"></i>
                            <span>Compras</span>
                        </a>
                    @endcan
                    @can('stock.reload')
                        <a href="{{ route('stock.reload') }}"
                           class="{{ request()->routeIs('stock.reload') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="rotate-cw" class="h-4 w-4"></i>
                            <span>Recarregar stock</span>
                        </a>
                    @endcan
                    @can('stock.movements.view')
                        <a href="{{ route('stock.movements') }}"
                           class="{{ request()->routeIs('stock.movements') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="arrow-right-left" class="h-4 w-4"></i>
                            <span>Movimentos stock</span>
                        </a>
                    @endcan
                    @can('stock.transfers.view')
                        <a href="{{ route('stock.transfers') }}"
                           class="{{ request()->routeIs('stock.transfers') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="truck" class="h-4 w-4"></i>
                            <span>Transferências</span>
                        </a>
                    @endcan
                </div>

                <div class="mb-5">
                    <p class="mb-2 px-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">Sistema</p>
                    @can('products.view')
                        <a href="{{ route('products.index') }}"
                           class="{{ request()->routeIs('products.*') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="box" class="h-4 w-4"></i>
                            <span>Produtos</span>
                        </a>
                    @endcan
                    @can('customers.view')
                        <a href="{{ route('customers.index') }}"
                           class="{{ request()->routeIs('customers.*') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="users" class="h-4 w-4"></i>
                            <span>Clientes</span>
                        </a>
                    @endcan
                    @can('reversals.view')
                        <a href="{{ route('reversals.index') }}"
                           class="{{ request()->routeIs('reversals.*') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="undo-2" class="h-4 w-4"></i>
                            <span>Reversões</span>
                        </a>
                    @endcan
                    @can('registers.view')
                        <a href="{{ route('registers.index') }}"
                           class="{{ request()->routeIs('registers.*') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="wallet" class="h-4 w-4"></i>
                            <span>Caixas</span>
                        </a>
                    @endcan
                    @can('stock_locations.view')
                        <a href="{{ route('stock-locations.index') }}"
                           class="{{ request()->routeIs('stock-locations.*') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="warehouse" class="h-4 w-4"></i>
                            <span>Armazéns/Locais</span>
                        </a>
                    @endcan
                    @can('users.view')
                        <a href="{{ route('users.index') }}"
                           class="{{ request()->routeIs('users.*') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="user-cog" class="h-4 w-4"></i>
                            <span>Utilizadores</span>
                        </a>
                    @endcan
                    @can('roles.view')
                        <a href="{{ route('roles.permissions') }}"
                           class="{{ request()->routeIs('roles.permissions') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="shield-check" class="h-4 w-4"></i>
                            <span>Roles & Permissões</span>
                        </a>
                    @endcan
                    @can('dashboard.view')
                        <a href="{{ route('settings.company') }}"
                           class="{{ request()->routeIs('settings.company') ? 'mb-1 flex items-center gap-2 rounded-lg bg-[color:rgba(216,182,90,0.16)] px-2.5 py-2 text-[13px] text-[var(--gold)] transition' : 'mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-slate-300 transition hover:bg-[var(--dark-soft)] hover:text-white' }}">
                            <i data-lucide="settings" class="h-4 w-4"></i>
                            <span>Configurações</span>
                        </a>
                    @endcan
                </div>
            </nav>

            <div class="border-t border-white/10 px-4 py-3">
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
                    <h2 class="text-xl font-bold text-slate-900">{{ $title ?? 'Painel administrativo' }}</h2>
                    <p class="text-xs text-slate-500">Gestão central do POS · {{ $dataAtual }}</p>
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
                            Sair
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
    @livewireScripts
</body>
</html>
