<div class="space-y-4">
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Histórico de vendas</p>
        <p class="text-sm text-slate-500">Monitorização das vendas concluídas no POS.</p>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <input wire:model.live.debounce.300ms="search" type="text" class="rp-input" placeholder="Pesquisar por referência, cliente ou método...">
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">Referência</th>
                    <th class="px-3 py-2">Cliente</th>
                    <th class="px-3 py-2">Pagamento</th>
                    <th class="px-3 py-2">Estado</th>
                    <th class="px-3 py-2">Total</th>
                    <th class="px-3 py-2">Data</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($vendas as $venda)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 font-medium">{{ $venda->referencia }}</td>
                        <td class="px-3 py-2">{{ $venda->cliente }}</td>
                        <td class="px-3 py-2">{{ $venda->metodo_pagamento }}</td>
                        <td class="px-3 py-2">{{ $venda->estado }}</td>
                        <td class="px-3 py-2">{{ number_format((float) $venda->total, 2, ',', '.') }} MZN</td>
                        <td class="px-3 py-2">{{ optional($venda->data)->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-slate-500">Sem vendas registadas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $vendas->links() }}</div>
</div>
