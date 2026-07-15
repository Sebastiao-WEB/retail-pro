@php
    $reversals_index_blade_routes = [
'index' => route('reversals.index'),
        'decide' => route('reversals.decide', ['reversalRequest' => '__ID__']),
        'pdf' => $pdfUrl,
    ];
@endphp

<x-layouts.desktop :title="__('pages.titles.reversals')" admin-page="reversals">
<div
    class="space-y-4"
    data-routes='@json($reversals_index_blade_routes)'
>
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.reversals.title') }}</p>
            <p class="text-sm text-slate-500">{{ __('pages.reversals.subtitle') }}</p>
        </div>
        <a href="{{ $pdfUrl }}" target="_blank" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
            <i data-lucide="file-down" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
            {{ __('pages.common.generate_pdf') }}
        </a>
    </div>

    <form method="GET" action="{{ route('reversals.index') }}" data-auto-submit data-reversal-filters class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.period_start') }}</label>
                <input name="periodo_inicio" type="date" value="{{ $periodo_inicio }}" class="rp-input">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.period_end') }}</label>
                <input name="periodo_fim" type="date" value="{{ $periodo_fim }}" class="rp-input">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.register') }}</label>
                <select name="registerFilter" class="rp-input">
                    <option value="">{{ __('app.all_registers') }}</option>
                    @foreach ($registers as $register)
                        <option value="{{ $register->id }}" @selected($registerFilter === $register->id)>{{ $register->name }} ({{ $register->code }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.status') }}</label>
                <select name="statusFilter" class="rp-input">
                    <option value="">{{ __('pages.common.all_statuses') }}</option>
                    <option value="PENDING" @selected($statusFilter === 'PENDING')>{{ \App\Support\Translations::reversalStatus('PENDING') }}</option>
                    <option value="APPROVED" @selected($statusFilter === 'APPROVED')>{{ \App\Support\Translations::reversalStatus('APPROVED') }}</option>
                    <option value="REJECTED" @selected($statusFilter === 'REJECTED')>{{ \App\Support\Translations::reversalStatus('REJECTED') }}</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="button" data-action="apply-this-month" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold hover:bg-slate-50">{{ __('pages.common.this_month') }}</button>
                <button type="button" data-action="apply-previous-month" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold hover:bg-slate-50">{{ __('pages.common.previous_month') }}</button>
            </div>
        </div>
    </form>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
        <div class="md:col-span-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pages.common.period_summary') }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-[10px] uppercase text-slate-500">{{ __('pages.common.total') }}</p>
            <p class="text-lg font-semibold">{{ number_format($totais['total'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
            <p class="text-[10px] uppercase text-amber-700">{{ __('pages.common.pending') }}</p>
            <p class="text-lg font-semibold text-amber-800">{{ number_format($totais['pendentes'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-[10px] uppercase text-emerald-700">{{ __('pages.common.approved') }}</p>
            <p class="text-lg font-semibold text-emerald-800">{{ number_format($totais['aprovadas'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg border border-red-200 bg-red-50 p-4">
            <p class="text-[10px] uppercase text-red-700">{{ __('pages.common.rejected') }}</p>
            <p class="text-lg font-semibold text-red-800">{{ number_format($totais['rejeitadas'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-[10px] uppercase text-slate-500">{{ __('pages.common.reversed_value') }}</p>
            <p class="text-lg font-semibold">{{ number_format($totais['valor_revertido'], 2, ',', '.') }} MT</p>
        </div>
    </div>

    <form method="GET" action="{{ route('reversals.index') }}" data-auto-submit data-debounce="300" class="rounded-lg border border-slate-200 bg-white p-4">
        @foreach (request()->except('search', 'page') as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <input name="search" type="text" value="{{ $search }}" class="rp-input" placeholder="{{ __('pages.reversals.search_placeholder') }}">
    </form>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">{{ __('app.fields.reference') }}</th>
                    <th class="px-3 py-2">{{ __('app.status') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.operator') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('pages.common.value') }}</th>
                    <th class="px-3 py-2">{{ __('pages.common.reason') }}</th>
                    <th class="px-3 py-2">{{ __('pages.common.requested_at') }}</th>
                    <th class="px-3 py-2">{{ __('pages.common.decided_at') }}</th>
                    @can('reversals.manage')
                        <th class="px-3 py-2">{{ __('app.actions') }}</th>
                    @endcan
                </tr>
            </thead>
            <tbody>
                @forelse ($reversoes as $reversao)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 font-medium">{{ $reversao->sale?->referencia ?? $reversao->sale_id }}</td>
                        <td class="px-3 py-2">
                            <span class="@if($reversao->status === 'APPROVED') text-emerald-600 @elseif($reversao->status === 'REJECTED') text-red-600 @else text-amber-600 @endif">
                                {{ \App\Support\Translations::reversalStatus($reversao->status) }}
                            </span>
                        </td>
                        <td class="px-3 py-2">{{ $reversao->sale?->operador ?? '—' }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) ($reversao->sale?->total ?? 0), 2, ',', '.') }} MT</td>
                        <td class="px-3 py-2">{{ $reversao->reason ?: '---' }}</td>
                        <td class="px-3 py-2">{{ optional($reversao->requested_at)->format('d/m/Y H:i') }}</td>
                        <td class="px-3 py-2">{{ optional($reversao->decided_at)->format('d/m/Y H:i') ?: '---' }}</td>
                        @can('reversals.manage')
                            <td class="px-3 py-2">
                                @if ($reversao->status === 'PENDING')
                                    <div class="flex items-center gap-2">
                                        <button type="button" data-action="open-decision" data-id="{{ $reversao->id }}" data-status="APPROVED" data-reference="{{ $reversao->sale?->referencia ?? $reversao->sale_id }}" class="rounded-md border border-emerald-200 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50">
                                            <i data-lucide="check" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
                                            {{ __('pages.common.approve') }}
                                        </button>
                                        <button type="button" data-action="open-decision" data-id="{{ $reversao->id }}" data-status="REJECTED" data-reference="{{ $reversao->sale?->referencia ?? $reversao->sale_id }}" class="rounded-md border border-red-200 px-2 py-1 text-xs text-red-600 hover:bg-red-50">
                                            <i data-lucide="x" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
                                            {{ __('pages.common.reject') }}
                                        </button>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400">{{ __('pages.common.completed') }}</span>
                                @endif
                            </td>
                        @endcan
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManage ? 8 : 7 }}" class="px-3 py-6 text-center text-slate-500">
                            @if ($search !== '' || $statusFilter !== '' || $registerFilter !== '')
                                {{ __('pages.common.no_filter_results') }}
                            @else
                                {{ __('pages.reversals.no_reversals') }}
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($reversoes->hasPages())
        <div>{{ $reversoes->links() }}</div>
    @endif

    @can('reversals.manage')
        <div id="reversal-decision-modal" class="rp-admin-modal hidden fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4" aria-hidden="true">
            <div class="w-full max-w-lg rounded-xl bg-white shadow-xl">
                <div class="border-b border-slate-200 px-5 py-3">
                    <h3
                        id="reversal-decision-title"
                        class="text-base font-semibold text-slate-900"
                        data-approve-title="{{ __('pages.reversals.approve_title') }}"
                        data-reject-title="{{ __('pages.reversals.reject_title') }}"
                    ></h3>
                </div>
                <form id="reversal-decision-form" class="p-5">
                    <input type="hidden" name="reversal_id" id="reversal-decision-id" value="">
                    <input type="hidden" name="decisionStatus" id="reversal-decision-status" value="">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.reason_optional') }}</label>
                    <textarea name="decisionReason" rows="4" class="rp-input" placeholder="{{ __('pages.common.decision_note_placeholder') }}"></textarea>
                    <p data-field-error="decisionReason" class="mt-1 hidden text-xs text-red-600"></p>
                    <div class="mt-4 flex justify-end gap-2 border-t border-slate-200 pt-3">
                        <button type="button" data-modal-close="reversal-decision-modal" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-100">
                            <i data-lucide="x" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.cancel') }}
                        </button>
                        <button type="submit" data-action="apply-decision" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                            <i data-lucide="check-check" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.confirm') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
</div>
</x-layouts.desktop>
