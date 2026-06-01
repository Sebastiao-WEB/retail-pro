<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.products.title') }}</p>
            <p class="text-sm text-slate-500">{{ __('pages.products.subtitle') }}</p>
        </div>
        @can('products.manage')
            <button type="button" wire:click="openCreateModal" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                <i data-lucide="plus" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
                {{ __('pages.products.new') }}
            </button>
        @endcan
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <input wire:model.live.debounce.300ms="search" type="text" class="rp-input" placeholder="{{ __('pages.products.search_placeholder') }}">
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">{{ __('app.fields.name') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.code') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.category') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.sale_price') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.iva') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.stock') }}</th>
                    <th class="px-3 py-2">{{ __('app.status') }}</th>
                    @can('products.manage')
                        <th class="px-3 py-2">{{ __('app.actions') }}</th>
                    @endcan
                </tr>
            </thead>
            <tbody>
                @forelse ($produtos as $produto)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 font-medium">{{ $produto->nome }}</td>
                        <td class="px-3 py-2">{{ $produto->codigo_barras ?: '---' }}</td>
                        <td class="px-3 py-2">{{ $produto->categoria ?: '---' }}</td>
                        <td class="px-3 py-2">{{ number_format((float) $produto->preco_venda, 2, ',', '.') }} {{ __('app.currency') }}</td>
                        <td class="px-3 py-2">
                            @if ($produto->iva_tipo === 'PERCENTUAL')
                                {{ number_format((float) $produto->iva_percentual, 2, ',', '.') }}%
                            @elseif ($produto->iva_tipo === 'MONETARIO')
                                {{ number_format((float) $produto->iva_valor, 2, ',', '.') }} {{ __('app.currency') }}
                            @else
                                {{ __('app.exempt') }}
                            @endif
                        </td>
                        <td class="px-3 py-2">{{ number_format((float) $produto->stock, 2, ',', '.') }}</td>
                        <td class="px-3 py-2">
                            <span class="{{ $produto->is_active ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ $produto->is_active ? __('app.active') : __('app.inactive') }}
                            </span>
                        </td>
                        @can('products.manage')
                            <td class="px-3 py-2">
                                <button type="button" wire:click="openEditModal('{{ $produto->id }}')" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50"><i data-lucide="pencil" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.edit') }}</button>
                            </td>
                        @endcan
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ auth()->user()?->can('products.manage') ? 8 : 7 }}" class="px-3 py-6 text-center text-slate-500">{{ __('pages.products.no_products') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $produtos->links() }}</div>

    @can('products.manage')
    @if ($modalOpen)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4">
            <div class="w-full max-w-2xl rounded-xl bg-white shadow-xl">
                <div class="border-b border-slate-200 px-5 py-3">
                    <h3 class="text-base font-semibold text-slate-900">{{ $editingId ? __('pages.products.edit') : __('pages.products.new') }}</h3>
                </div>
                <div class="grid grid-cols-1 gap-3 p-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.name') }}</label>
                        <input id="campo-nome" wire:model.defer="nome" type="text" class="rp-input @error('nome') border-red-500 ring-1 ring-red-300 @enderror">
                        @error('nome') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600" for="campo-codigo_barras">{{ __('app.fields.barcode') }}</label>
                        <input
                            id="campo-codigo_barras"
                            wire:model.defer="codigo_barras"
                            type="text"
                            class="rp-input @error('codigo_barras') border-red-500 ring-1 ring-red-300 @enderror"
                        >
                        @error('codigo_barras')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.category') }}</label>
                        <input wire:model.defer="categoria" type="text" class="rp-input">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.purchase_price') }}</label>
                        <input wire:model.defer="preco_compra" type="number" step="0.01" class="rp-input">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.sale_price') }}</label>
                        <input wire:model.defer="preco_venda" type="number" step="0.01" class="rp-input">
                        @error('preco_venda') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.iva_type') }}</label>
                        <select wire:model.defer="iva_tipo" class="rp-input">
                            <option value="ISENTO">ISENTO</option>
                            <option value="PERCENTUAL">PERCENTUAL</option>
                            <option value="MONETARIO">MONETARIO</option>
                        </select>
                        @error('iva_tipo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    @if ($iva_tipo === 'PERCENTUAL')
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.iva_percent') }}</label>
                            <input wire:model.defer="iva_percentual" type="number" step="0.01" class="rp-input" placeholder="16.00">
                            @error('iva_percentual') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @elseif ($iva_tipo === 'MONETARIO')
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.iva_amount') }}</label>
                            <input wire:model.defer="iva_valor" type="number" step="0.01" class="rp-input" placeholder="5.00">
                            @error('iva_valor') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                            {{ __('pages.products.iva_exempt_note') }}
                        </div>
                    @endif
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.stock') }}</label>
                        @if ($editingId)
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                <span class="font-semibold text-slate-800">{{ number_format((float) $stockAtual, 2, ',', '.') }} {{ __('pages.products.units') }}</span>
                                <span class="text-slate-500"> — {{ __('pages.products.stock_readonly') }}</span>
                            </div>
                        @else
                            <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                                {{ __('pages.products.stock_initial') }}
                            </div>
                        @endif
                    </div>
                    <div class="md:col-span-2">
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input id="produto-ativo" wire:model.defer="is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-amber-600">
                            {{ __('pages.products.active_pos') }}
                        </label>
                        <p class="mt-1 text-xs text-slate-500">{{ __('pages.products.active_pos_hint') }}</p>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                    <button type="button" wire:click="closeModal" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-100"><i data-lucide="x" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.cancel') }}</button>
                    <button type="button" wire:click="save" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95"><i data-lucide="save" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.save') }}</button>
                </div>
            </div>
        </div>
    @endif
    @endcan
</div>
