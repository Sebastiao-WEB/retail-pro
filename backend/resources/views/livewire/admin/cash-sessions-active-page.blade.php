<div class="space-y-4">
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.cash_sessions.active_title') }}</p>
        <p class="text-sm text-slate-500">{{ __('pages.cash_sessions.active_subtitle') }}</p>
    </div>

    <div class="grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-2">
        <input wire:model.live.debounce.300ms="search" type="text" class="rp-input" placeholder="{{ __('pages.cash_sessions.active_search_placeholder') }}">
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
                            <button type="button" wire:click="openDetail('{{ $sessao->id }}')" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">{{ __('app.details') }}</button>
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

    @if ($detailModalOpen && $detalhe)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4">
            <div class="w-full max-w-lg rounded-xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                    <h3 class="text-base font-semibold">{{ __('pages.common.active_session', ['name' => $detalhe->register?->name]) }}</h3>
                    <button type="button" wire:click="closeDetail" class="text-slate-500 hover:text-slate-800">✕</button>
                </div>
                <div class="space-y-2 p-5 text-sm">
                    <p><strong>{{ __('pages.common.session_id') }}:</strong> {{ $detalhe->id }}</p>
                    <p><strong>{{ __('app.fields.register') }}:</strong> {{ $detalhe->register?->code }} — {{ $detalhe->register?->name }}</p>
                    <p><strong>{{ __('app.fields.operator') }}:</strong> {{ $detalhe->user?->name ?? '—' }}</p>
                    <p><strong>{{ __('pages.common.opened_at') }}:</strong> {{ optional($detalhe->opened_at)->format('d/m/Y H:i') ?? '—' }}</p>
                    <p><strong>{{ __('pages.common.opening_balance') }}:</strong> {{ number_format((float) $detalhe->opening_balance, 2, ',', '.') }} MT</p>
                </div>
            </div>
        </div>
    @endif
</div>
