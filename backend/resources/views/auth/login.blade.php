<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar | RetailPro</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--bg-app)]">
    <section class="flex min-h-screen items-center justify-center p-6">
        <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-5 text-center">
                <h1 class="text-2xl font-bold text-slate-900">RetailPro POS</h1>
                <p class="text-sm text-slate-500">Inicio de sessao do backoffice</p>
            </div>

            @php
                $toastStatus = session('status');
                $toastError = $errors->any() ? $errors->first() : null;
            @endphp

            <form method="POST" action="{{ route('login') }}" class="space-y-3">
                @csrf
                <div>
                    <label for="username" class="mb-1 block text-xs font-semibold text-slate-600">Utilizador</label>
                    <input id="username" name="username" type="text" required autofocus autocomplete="username" class="rp-input" placeholder="username">
                </div>

                <div>
                    <label for="password" class="mb-1 block text-xs font-semibold text-slate-600">Senha / PIN</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password" class="rp-input" placeholder="••••••">
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                    Lembrar sessao
                </label>

                <button type="submit" class="w-full rounded-lg bg-[var(--gold)] px-4 py-2.5 text-sm font-semibold text-black transition hover:brightness-95">
                    Entrar no sistema
                </button>
            </form>
        </div>
    </section>

    <script>
        window.addEventListener('load', () => {
            if (typeof window.retailToast !== 'function') return;

            @if ($toastError)
                window.retailToast(@js($toastError), 'error');
            @elseif ($toastStatus)
                window.retailToast(@js($toastStatus), 'success');
            @endif
        });
    </script>
</body>
</html>
