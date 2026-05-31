<!doctype html>
<html lang="{{ \App\Support\SupportedLocales::htmlLang(app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('auth.login_title') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--bg-app)]">
    <section class="relative flex min-h-screen items-center justify-center p-6">
        <div class="absolute right-6 top-6">
            <x-language-switcher />
        </div>

        <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-5 text-center">
                <img src="{{ asset('assets/images/rp.png') }}" alt="RetailPro POS" class="mx-auto h-35 w-auto object-contain">
            </div>

            {{ $slot }}
        </div>
    </section>

    @php
        $toastStatus = session('status');
        $toastError = $errors->any() ? $errors->first() : null;
        $statusMessage = null;
        if ($toastStatus) {
            $statusKey = 'auth.fortify_status.'.$toastStatus;
            $translated = __($statusKey);
            $statusMessage = $translated !== $statusKey ? $translated : $toastStatus;
        }
    @endphp

    <script>
        window.addEventListener('load', () => {
            if (typeof window.retailToast !== 'function') return;

            @if ($toastError)
                window.retailToast(@js($toastError), 'error');
            @elseif ($statusMessage)
                window.retailToast(@js($statusMessage), 'success');
            @endif
        });
    </script>
</body>
</html>
