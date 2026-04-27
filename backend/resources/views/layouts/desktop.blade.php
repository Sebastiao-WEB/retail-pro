<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'RetailPro Backoffice' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-[var(--bg-app)] text-slate-900">
    <div class="flex min-h-screen">
        <aside class="w-64 bg-[var(--dark)] p-4 text-slate-200">
            <div class="mb-6 flex items-center gap-3 rounded-lg border border-white/10 px-3 py-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[var(--gold)] text-sm font-black text-black">R</div>
                <div>
                    <p class="text-sm font-bold">RetailPro Backoffice</p>
                    <p class="text-[11px] text-slate-400">Laravel + Livewire</p>
                </div>
            </div>
            <nav class="space-y-1 text-sm">
                <a href="{{ route('dashboard') }}" class="block rounded-md px-3 py-2 hover:bg-[var(--dark-soft)]">Dashboard</a>
            </nav>
        </aside>

        <main class="flex-1 p-6">
            <div class="rp-card min-h-[calc(100vh-3rem)] p-6">
                {{ $slot }}
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

    @livewireScripts
</body>
</html>
