@php
    $listaFiltros = array_filter([
        'search' => $search,
        'registerFilter' => $registerFilter,
    ], fn ($value) => $value !== '' && $value !== null);
@endphp

<x-layouts.desktop :title="__('pages.titles.cash_sessions_active')" admin-page="cash-sessions-active">
<div class="space-y-4">
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.cash_sessions.active_title') }}</p>
        <p class="text-sm text-slate-500">{{ __('pages.cash_sessions.active_subtitle') }}</p>
    </div>

    <form method="GET" action="{{ route('cash-sessions.active') }}" data-auto-submit data-debounce="300" class="grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-2">
        <input name="search" type="text" value="{{ $search }}" class="rp-input" placeholder="{{ __('pages.cash_sessions.active_search_placeholder') }}">
        <select name="registerFilter" class="rp-input">
            <option value="">{{ __('app.all_registers') }}</option>
            @foreach ($registers as $register)
                <option value="{{ $register->id }}" @selected($registerFilter === $register->id)>{{ $register->code }} — {{ $register->name }}</option>
            @endforeach
        </select>
    </form>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">{{ __('pages.common.opened_at') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.register') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.operator') }}</th>
                    <th class="px-3 py-2">{{ __('pages.common.opening_balance') }}</th>
                    <th class="px-3 py-2">{{ __('app.status') }}</th>
                    <th class="px-3 py-2">{{ __('app.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sessoes as $sessao)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2">{{ optional($sessao->opened_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="px-3 py-2 font-medium">{{ $sessao->register?->name ?? '—' }} <span class="text-xs text-slate-500">({{ $sessao->register?->code }})</span></td>
                        <td class="px-3 py-2">{{ $sessao->user?->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ number_format((float) $sessao->opening_balance, 2, ',', '.') }} MT</td>
                        <td class="px-3 py-2"><span class="font-semibold text-emerald-600">{{ __('pages.common.status_open') }}</span></td>
                        <td class="px-3 py-2">
                            <a href="{{ route('cash-sessions.detail', array_merge(['cashSession' => $sessao, 'from' => 'active'], $listaFiltros)) }}" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">{{ __('app.details') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-slate-500">{{ __('pages.cash_sessions.active_no_sessions') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $sessoes->links() }}</div>
</div>
</x-layouts.desktop>
