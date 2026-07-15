@props([
    'code' => '500',
    'errorKey' => null,
    'title' => '',
    'message' => '',
    'hint' => null,
])

@php
    app()->setLocale(\App\Support\SupportedLocales::DEFAULT);

    if ($errorKey) {
        $title = __('errors.'.$errorKey.'.title');
        $message = __('errors.'.$errorKey.'.message');
        $hint = __('errors.'.$errorKey.'.hint');
    }

    $homeUrl = auth()->check() ? url('/') : route('login');
    $homeLabel = auth()->check() ? __('errors.go_home') : __('errors.go_login');
@endphp

<!doctype html>
<html lang="pt-MZ">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('errors.page_title', ['code' => $code]) }}</title>
    <style>
        :root {
            --bg-app: #e8eaef;
            --dark: #11131a;
            --dark-soft: #1a1d27;
            --gold: #d8b65a;
            --gold-soft: #f4ead0;
            --text: #111827;
            --muted: #6b7280;
            --border: #e3e6ec;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Inter", "Segoe UI", Arial, sans-serif;
            color: var(--text);
            background: var(--bg-app);
        }

        .rp-error-shell {
            position: relative;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            overflow: hidden;
        }

        .rp-error-bg {
            position: absolute;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(circle at 15% 20%, rgba(216, 182, 90, 0.18), transparent 32%),
                radial-gradient(circle at 85% 80%, rgba(17, 19, 26, 0.08), transparent 36%),
                linear-gradient(180deg, #eef1f6 0%, var(--bg-app) 45%, #dfe3eb 100%);
        }

        .rp-error-bg::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(17, 19, 26, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(17, 19, 26, 0.03) 1px, transparent 1px);
            background-size: 28px 28px;
            mask-image: radial-gradient(circle at center, black 20%, transparent 78%);
        }

        .rp-error-card {
            position: relative;
            z-index: 1;
            width: min(100%, 34rem);
            border-radius: 1.25rem;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.94);
            box-shadow:
                0 24px 60px rgba(15, 23, 42, 0.12),
                0 0 0 1px rgba(255, 255, 255, 0.65) inset;
            backdrop-filter: blur(10px);
            padding: 2rem 2rem 1.75rem;
            text-align: center;
        }

        .rp-error-logo-wrap {
            display: flex;
            justify-content: center;
            margin-bottom: 1.25rem;
        }

        .rp-error-logo-wrap img {
            width: auto;
            height: 5.5rem;
            object-fit: contain;
        }

        .rp-error-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.85rem;
            padding: 0.35rem 0.8rem;
            border-radius: 999px;
            background: var(--gold-soft);
            color: #7a5d14;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .rp-error-code {
            font-size: clamp(3.5rem, 12vw, 5.5rem);
            line-height: 0.95;
            font-weight: 800;
            letter-spacing: -0.04em;
            background: linear-gradient(135deg, #b88d2f 0%, var(--gold) 45%, #f0d48a 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin: 0 0 0.35rem;
        }

        .rp-error-title {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--dark);
        }

        .rp-error-message {
            margin: 0.75rem 0 0;
            font-size: 0.95rem;
            line-height: 1.6;
            color: var(--muted);
        }

        .rp-error-hint {
            margin: 0.85rem 0 0;
            padding: 0.8rem 0.95rem;
            border-radius: 0.75rem;
            border: 1px solid #f0e2b8;
            background: #fffaf0;
            color: #7a651f;
            font-size: 0.82rem;
            line-height: 1.5;
        }

        .rp-error-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            justify-content: center;
            margin-top: 1.5rem;
        }

        .rp-error-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 8.5rem;
            padding: 0.72rem 1rem;
            border-radius: 0.65rem;
            font-size: 0.84rem;
            font-weight: 700;
            text-decoration: none;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }

        .rp-error-btn:hover {
            transform: translateY(-1px);
        }

        .rp-error-btn-secondary {
            border: 1px solid #d8dde6;
            background: #fff;
            color: #334155;
        }

        .rp-error-btn-secondary:hover {
            background: #f8fafc;
        }

        .rp-error-btn-primary {
            border: 1px solid #c8ab5b;
            background: linear-gradient(180deg, #e4c36f 0%, var(--gold) 100%);
            color: #11131a;
            box-shadow: 0 8px 18px rgba(216, 182, 90, 0.28);
        }

        .rp-error-btn-primary:hover {
            box-shadow: 0 10px 22px rgba(216, 182, 90, 0.34);
        }

        .rp-error-footer {
            position: relative;
            z-index: 1;
            margin-top: 1.25rem;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="rp-error-shell">
        <div class="rp-error-bg" aria-hidden="true"></div>

        <div class="rp-error-card">
            <div class="rp-error-logo-wrap">
                <img src="{{ asset('assets/images/rp.png') }}" alt="{{ __('errors.badge') }}">
            </div>

            <div class="rp-error-badge">{{ __('errors.badge') }}</div>

            <p class="rp-error-code" aria-hidden="true">{{ $code }}</p>
            <h1 class="rp-error-title">{{ $title }}</h1>
            <p class="rp-error-message">{{ $message }}</p>

            @if ($hint)
                <p class="rp-error-hint">{{ $hint }}</p>
            @endif

            <div class="rp-error-actions">
                <a href="javascript:history.back()" class="rp-error-btn rp-error-btn-secondary">{{ __('errors.go_back') }}</a>
                <a href="{{ $homeUrl }}" class="rp-error-btn rp-error-btn-primary">{{ $homeLabel }}</a>
            </div>
        </div>

        <p class="rp-error-footer">{{ __('errors.badge') }}</p>
    </div>
</body>
</html>
