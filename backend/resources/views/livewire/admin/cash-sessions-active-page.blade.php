<div class="space-y-4">
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Sessões de caixa activas</p>
        <p class="text-sm text-slate-500">Turnos abertos em todos os caixas da operação.</p>
    </div>

    <div class="grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-2">
        <input wire:model.live.debounce.300ms="search" type="text" class="rp-input" placeholder="Pesquisar por caixa ou operador...">
        <select wire:model.live="registerFilter" class="rp-input">
            <option value="">Todos os caixas</option>
            @foreach ($registers as $register)
                <option value="{{ $register->id }}">{{ $register->code }} — {{ $register->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">Aberto em</th>
                    <th class="px-3 py-2">Caixa</th>
                    <th class="px-3 py-2">Operador</th>
                    <th class="px-3 py-2">Fundo inicial</th>
                    <th class="px-3 py-2">Estado</th>
                    <th class="px-3 py-2">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sessoes as $sessao)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2">{{ optional($sessao->opened_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="px-3 py-2 font-medium">{{ $sessao->register?->name ?? '—' }} <span class="text-xs text-slate-500">({{ $sessao->register?->code }})</span></td>
                        <td class="px-3 py-2">{{ $sessao->user?->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ number_format((float) $sessao->opening_balance, 2, ',', '.') }} MT</td>
                        <td class="px-3 py-2"><span class="font-semibold text-emerald-600">OPEN</span></td>
                        <td class="px-3 py-2">
                            <button type="button" wire:click="openDetail('{{ $sessao->id }}')" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">Detalhes</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-slate-500">Nenhuma sessão de caixa activa.</td>
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
                    <h3 class="text-base font-semibold">Sessão activa — {{ $detalhe->register?->name }}</h3>
                    <button type="button" wire:click="closeDetail" class="text-slate-500 hover:text-slate-800">✕</button>
                </div>
                <div class="space-y-2 p-5 text-sm">
                    <p><strong>ID sessão:</strong> {{ $detalhe->id }}</p>
                    <p><strong>Caixa:</strong> {{ $detalhe->register?->code }} — {{ $detalhe->register?->name }}</p>
                    <p><strong>Operador:</strong> {{ $detalhe->user?->name ?? '—' }}</p>
                    <p><strong>Aberto em:</strong> {{ optional($detalhe->opened_at)->format('d/m/Y H:i') ?? '—' }}</p>
                    <p><strong>Fundo inicial:</strong> {{ number_format((float) $detalhe->opening_balance, 2, ',', '.') }} MT</p>
                </div>
            </div>
        </div>
    @endif
</div>
