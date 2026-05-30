<div class="space-y-4">
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Extrato de movimentos de stock</p>
        <p class="text-sm text-slate-500">Histórico completo de recargas, saídas, transferências e ajustes.</p>
    </div>

    <div class="grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-4">
        <input wire:model.live.debounce.300ms="search" type="text" class="rp-input" placeholder="Pesquisar por produto ou código...">
        <select wire:model.live="typeFilter" class="rp-input">
            <option value="">Todos os tipos</option>
            <option value="IN">Entrada (IN)</option>
            <option value="OUT">Saída (OUT)</option>
            <option value="TRANSFER">Transferência</option>
            <option value="ADJUSTMENT">Ajuste</option>
            <option value="RETURN">Devolução</option>
        </select>
        <select wire:model.live="locationFilter" class="rp-input">
            <option value="">Todas as localizações</option>
            @foreach ($locations as $location)
                <option value="{{ $location->id }}">{{ $location->code }} - {{ $location->name }}</option>
            @endforeach
        </select>
        <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
            <input wire:model.live="reloadsOnly" type="checkbox" class="rounded border-slate-300">
            Apenas recargas
        </label>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">Data</th>
                    <th class="px-3 py-2">Produto</th>
                    <th class="px-3 py-2">Tipo</th>
                    <th class="px-3 py-2">Qtd</th>
                    <th class="px-3 py-2">Origem</th>
                    <th class="px-3 py-2">Destino</th>
                    <th class="px-3 py-2">Operador</th>
                    <th class="px-3 py-2">Nota</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movements as $movement)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2">{{ optional($movement->created_at)->format('d/m/Y H:i') }}</td>
                        <td class="px-3 py-2 font-medium">{{ $movement->product?->nome ?? $movement->product_id }}</td>
                        <td class="px-3 py-2">
                            @if (in_array($movement->reference_type, ['PURCHASE', 'STOCK_RELOAD'], true) && $movement->type === 'IN')
                                Recarga
                            @else
                                {{ $movement->type }}
                            @endif
                        </td>
                        <td class="px-3 py-2">{{ number_format((float) $movement->quantity, 2, ',', '.') }}</td>
                        <td class="px-3 py-2">{{ $movement->fromLocation?->name ?? '---' }}</td>
                        <td class="px-3 py-2">{{ $movement->toLocation?->name ?? '---' }}</td>
                        <td class="px-3 py-2">{{ $movement->performedBy?->name ?? '---' }}</td>
                        <td class="px-3 py-2">{{ $movement->note ?: '---' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-6 text-center text-slate-500">Sem movimentos de stock.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $movements->links() }}</div>
</div>
