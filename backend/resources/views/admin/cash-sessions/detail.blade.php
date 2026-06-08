@php
    $registerName = $session->register?->name ?? ($snapshot['caixa'] ?? '—');
    $registerCode = $session->register?->code ?? '—';
    $operatorName = $session->user?->name ?? ($snapshot['utilizador'] ?? '—');
    $detailTitle = $isClosed
        ? __('pages.common.close_detail_title', ['name' => $registerName])
        : __('pages.common.active_session', ['name' => $registerName]);
@endphp

<x-layouts.desktop :title="__('pages.titles.cash_sessions_detail')" admin-page="cash-sessions-detail">
<div class="mx-auto max-w-3xl space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $detailTitle }}</p>
            <p class="text-sm text-slate-500">{{ $operatorName }} · {{ $registerCode }}</p>
        </div>
        <a href="{{ $backUrl }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
            <i data-lucide="arrow-left" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.back') }}
        </a>
    </div>

    <div class="grid grid-cols-2 gap-3 rounded-lg border border-slate-200 bg-white p-5 text-sm md:grid-cols-3">
        <div>
            <p class="text-xs text-slate-500">{{ __('pages.common.opened_at') }}</p>
            <p class="font-medium">{{ optional($session->opened_at)->format('d/m/Y H:i') ?? '—' }}</p>
        </div>
        @if ($isClosed)
            <div>
                <p class="text-xs text-slate-500">{{ __('pages.common.closed_at') }}</p>
                <p class="font-medium">{{ optional($session->closed_at)->format('d/m/Y H:i') ?? '—' }}</p>
            </div>
        @endif
        <div>
            <p class="text-xs text-slate-500">{{ __('pages.common.opening_balance') }}</p>
            <p class="font-medium">{{ number_format((float) $session->opening_balance, 2, ',', '.') }} MT</p>
        </div>
        @if ($isClosed)
            <div>
                <p class="text-xs text-slate-500">{{ __('pages.common.counted_cash') }}</p>
                <p class="font-medium">{{ number_format((float) ($session->closing_balance ?? 0), 2, ',', '.') }} MT</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">{{ __('pages.common.expected_cash') }}</p>
                <p class="font-medium">{{ number_format((float) ($snapshot['dinheiroEsperado'] ?? $snapshot['expected_cash'] ?? 0), 2, ',', '.') }} MT</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">{{ __('pages.common.difference') }}</p>
                <p class="font-medium {{ (float) ($session->difference_amount ?? 0) === 0.0 ? 'text-emerald-600' : 'text-amber-700' }}">
                    {{ number_format((float) ($session->difference_amount ?? 0), 2, ',', '.') }} MT
                </p>
            </div>
        @endif
        <div>
            <p class="text-xs text-slate-500">{{ __('pages.common.total_sold') }}</p>
            <p class="font-medium">{{ number_format((float) ($snapshot['totalVendido'] ?? 0), 2, ',', '.') }} MT</p>
        </div>
        <div>
            <p class="text-xs text-slate-500">{{ __('app.status') }}</p>
            <p class="font-medium {{ $isClosed ? 'text-slate-700' : 'text-emerald-600' }}">{{ $session->status }}</p>
        </div>
    </div>

    @if (! empty($session->note))
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">{{ __('pages.common.justification') }}</p>
            <p class="mt-1 text-slate-700">{{ $session->note }}</p>
        </div>
    @endif
</div>
</x-layouts.desktop>
