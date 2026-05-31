<div class="space-y-4">
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.cash_sessions.closed_title') }}</p>
        <p class="text-sm text-slate-500">{{ __('pages.cash_sessions.closed_subtitle') }}</p>
    </div>

    <div class="grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-2">
        <input wire:model.live.debounce.300ms="search" type="text" class="rp-input" placeholder="{{ __('pages.cash_sessions.closed_search_placeholder') }}">
        <select wire:model.live="registerFilter" class="rp-input">
            <option value="">{{ __('app.all_registers') }}</option>
            @foreach ($registers as $register)
                <option value="{{ $register->id }}">{{ $register->code }} — {{ $register->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">{{ __('pages.common.closed_at') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.register') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.operator') }}</th>
                    <th class="px-3 py-2">{{ __('pages.common.opening_time') }}</th>
                    <th class="px-3 py-2">{{ __('pages.common.total_sold') }}</th>
                    <th class="px-3 py-2">{{ __('pages.common.difference') }}</th>
                    <th class="px-3 py-2">{{ __('app.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($fechos as $fecho)
                    @php
                        $snapshot = is_array($fecho->report_snapshot) ? $fecho->report_snapshot : [];
                    @endphp
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2">{{ optional($fecho->closed_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="px-3 py-2 font-medium">{{ $fecho->register?->name ?? ($snapshot['caixa'] ?? '—') }}</td>
                        <td class="px-3 py-2">{{ $fecho->user?->name ?? ($snapshot['utilizador'] ?? '—') }}</td>
                        <td class="px-3 py-2">{{ optional($fecho->opened_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="px-3 py-2">{{ number_format((float) ($snapshot['totalVendido'] ?? 0), 2, ',', '.') }} MT</td>
                        <td class="px-3 py-2">
                            <span class="{{ (float) ($fecho->difference_amount ?? 0) === 0.0 ? 'text-emerald-600' : 'text-amber-700' }}">
                                {{ number_format((float) ($fecho->difference_amount ?? 0), 2, ',', '.') }} MT
                            </span>
                        </td>
                        <td class="px-3 py-2">
                            <button type="button" wire:click="openDetail('{{ $fecho->id }}')" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">{{ __('app.details') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-6 text-center text-slate-500">{{ __('pages.cash_sessions.closed_no_sessions') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $fechos->links() }}</div>

    @if ($detailModalOpen && $detalhe)
        @php $snap = is_array($detalhe->report_snapshot) ? $detalhe->report_snapshot : []; @endphp
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4">
            <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                    <h3 class="text-base font-semibold">{{ __('pages.common.close_detail_title', ['name' => $detalhe->register?->name ?? __('pages.common.default_register')]) }}</h3>
                    <button type="button" wire:click="closeDetail" class="text-slate-500 hover:text-slate-800">✕</button>
                </div>
                <div class="space-y-3 p-5 text-sm">
                    <p><strong>{{ __('app.fields.operator') }}:</strong> {{ $detalhe->user?->name ?? ($snap['utilizador'] ?? '—') }}</p>
                    <p><strong>{{ __('pages.common.opening_time') }}:</strong> {{ optional($detalhe->opened_at)->format('d/m/Y H:i') ?? '—' }}</p>
                    <p><strong>{{ __('pages.common.closing_label') }}:</strong> {{ optional($detalhe->closed_at)->format('d/m/Y H:i') ?? '—' }}</p>
                    <p><strong>{{ __('pages.common.opening_balance') }}:</strong> {{ number_format((float) ($snap['fundoInicial'] ?? $detalhe->opening_balance ?? 0), 2, ',', '.') }} MT</p>
                    <p><strong>{{ __('pages.common.total_sold') }}:</strong> {{ number_format((float) ($snap['totalVendido'] ?? 0), 2, ',', '.') }} MT</p>
                    <p><strong>{{ __('pages.common.transactions') }}:</strong> {{ $snap['totalTransacoes'] ?? '—' }}</p>
                    <p><strong>{{ __('pages.common.cash_sales') }}:</strong> {{ number_format((float) ($snap['vendasDinheiro'] ?? 0), 2, ',', '.') }} MT</p>
                    <p><strong>{{ __('pages.common.expected_cash') }}:</strong> {{ number_format((float) ($snap['dinheiroEsperado'] ?? 0), 2, ',', '.') }} MT</p>
                    <p><strong>{{ __('pages.common.counted_cash') }}:</strong> {{ number_format((float) ($snap['dinheiroReal'] ?? $detalhe->closing_balance ?? 0), 2, ',', '.') }} MT</p>
                    <p><strong>{{ __('pages.common.difference') }}:</strong> {{ number_format((float) ($snap['diferenca'] ?? $detalhe->difference_amount ?? 0), 2, ',', '.') }} MT</p>
                    @if (! empty($snap['justificativaDiferenca']) || $detalhe->note)
                        <p><strong>{{ __('pages.common.justification') }}:</strong> {{ $snap['justificativaDiferenca'] ?? $detalhe->note }}</p>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
