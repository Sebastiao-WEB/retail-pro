@php
    $stock_locations_index_blade_routes = [
        'index' => route('stock-locations.index'),
        'store' => route('stock-locations.store'),
        'update' => route('stock-locations.update', ['stockLocation' => '__ID__']),
        'destroy' => route('stock-locations.destroy', ['stockLocation' => '__ID__']),
    ];
@endphp

<x-layouts.desktop :title="__('pages.titles.stock_locations')" admin-page="stock-locations">
<div
    class="space-y-4"
    data-routes='@json($stock_locations_index_blade_routes)'
>
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.stock_locations.title') }}</p>
            <p class="text-sm text-slate-500">{{ __('pages.stock_locations.subtitle') }}</p>
        </div>
        @can('stock_locations.manage')
            <button type="button" data-action="open-create" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                <i data-lucide="plus" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
                {{ __('pages.stock_locations.new') }}
            </button>
        @endcan
    </div>

    <form method="GET" action="{{ route('stock-locations.index') }}" data-auto-submit data-debounce="300" class="rounded-lg border border-slate-200 bg-white p-4">
        <input name="search" type="text" value="{{ $search }}" class="rp-input" placeholder="{{ __('pages.stock_locations.search_placeholder') }}">
    </form>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">{{ __('app.fields.code') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.name') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.type') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.register') }}</th>
                    <th class="px-3 py-2">{{ __('pages.common.products_count') }}</th>
                    <th class="px-3 py-2">{{ __('pages.common.total_qty') }}</th>
                    <th class="px-3 py-2">{{ __('app.status') }}</th>
                    <th class="px-3 py-2">{{ __('app.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($locations as $location)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 font-medium">{{ $location->code }}</td>
                        <td class="px-3 py-2">{{ $location->name }}</td>
                        <td class="px-3 py-2">{{ $location->type }}</td>
                        <td class="px-3 py-2">{{ $location->register?->code ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $location->products_count ?? 0 }}</td>
                        <td class="px-3 py-2">{{ number_format((float) ($location->total_quantity ?? 0), 0, ',', '.') }}</td>
                        <td class="px-3 py-2">
                            <span class="{{ $location->is_active ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ $location->is_active ? __('app.active') : __('app.inactive') }}
                            </span>
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex flex-wrap items-center gap-1">
                                @can('stock_locations.view')
                                    <a
                                        href="{{ route('stock-locations.stock', ['stockLocation' => $location, 'search' => $search]) }}"
                                        data-rp-page-nav
                                        title="{{ __('pages.common.view_stock') }}"
                                        aria-label="{{ __('pages.common.view_stock') }}: {{ $location->name }}"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-emerald-200 text-emerald-700 hover:bg-emerald-50"
                                    >
                                        <i data-lucide="boxes" class="h-3.5 w-3.5"></i>
                                    </a>
                                @endcan
                                @can('stock_locations.manage')
                                    <a
                                        href="{{ route('stock-locations.edit', ['stockLocation' => $location, 'search' => $search]) }}"
                                        data-rp-page-nav
                                        title="{{ __('app.edit') }}"
                                        aria-label="{{ __('app.edit') }}: {{ $location->name }}"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 text-slate-700 hover:bg-slate-50"
                                    >
                                        <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                                    </a>
                                    @if ($location->is_active)
                                        <button
                                            type="button"
                                            data-action="confirm-delete"
                                            data-id="{{ $location->id }}"
                                            data-code="{{ $location->code }}"
                                            data-name="{{ $location->name }}"
                                            title="{{ __('app.disable') }}"
                                            aria-label="{{ __('app.disable') }}: {{ $location->name }}"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-red-200 text-red-600 hover:bg-red-50"
                                        >
                                            <i data-lucide="ban" class="h-3.5 w-3.5"></i>
                                        </button>
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-6 text-center text-slate-500">{{ __('pages.stock_locations.no_locations') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $locations->links() }}</div>

    @can('stock_locations.manage')
        <div id="stock-location-form-modal" class="rp-admin-modal hidden fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4" aria-hidden="true">
            <div class="w-full max-w-2xl rounded-xl bg-white shadow-xl">
                <div class="border-b border-slate-200 px-5 py-3">
                    <h3 id="stock-location-form-title" class="text-base font-semibold">{{ __('pages.stock_locations.new') }}</h3>
                </div>
                <form id="stock-location-form" class="grid grid-cols-1 gap-3 p-5 md:grid-cols-2">
                    <input type="hidden" name="editing_id" id="stock-location-editing-id" value="">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.code') }}</label>
                        <input name="code" type="text" class="rp-input" placeholder="{{ __('pages.stock_locations.code_placeholder') }}">
                        <p data-field-error="code" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.name') }}</label>
                        <input name="name" type="text" class="rp-input" placeholder="{{ __('pages.stock_locations.name_placeholder') }}">
                        <p data-field-error="name" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.type') }}</label>
                        <select name="type" class="rp-input">
                            <option value="STORE_FLOOR">STORE_FLOOR</option>
                            <option value="WAREHOUSE">WAREHOUSE</option>
                            <option value="DAMAGE">DAMAGE</option>
                            <option value="RETURN_AREA">RETURN_AREA</option>
                            <option value="TRANSIT">TRANSIT</option>
                        </select>
                        <p data-field-error="type" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.register') }}</label>
                        <select name="register_id" class="rp-input">
                            <option value="">{{ __('app.select') }}</option>
                            @foreach ($registers as $register)
                                <option value="{{ $register->id }}">{{ $register->code }} — {{ $register->name }}</option>
                            @endforeach
                        </select>
                        <p data-field-error="register_id" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div class="md:col-span-2 flex flex-wrap gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                            <input name="is_saleable" type="checkbox" value="1" checked class="h-4 w-4 rounded border-slate-300 text-amber-600">
                            {{ __('pages.common.saleable') }}
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                            <input name="is_active" type="checkbox" value="1" checked class="h-4 w-4 rounded border-slate-300 text-amber-600">
                            {{ __('app.active') }}
                        </label>
                    </div>
                    <div class="md:col-span-2 flex justify-end gap-2 border-t border-slate-200 pt-3">
                        <button type="button" data-modal-close="stock-location-form-modal" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">{{ __('app.cancel') }}</button>
                        <button type="submit" data-action="save-stock-location" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black">{{ __('app.save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="stock-location-delete-modal" class="rp-admin-modal hidden fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="stock-location-delete-title">
            <div class="w-full max-w-md rounded-xl bg-white shadow-xl">
                <div class="border-b border-slate-200 px-5 py-3">
                    <h3 id="stock-location-delete-title" class="text-base font-semibold text-slate-900">{{ __('pages.common.confirm_disable_title') }}</h3>
                </div>
                <div class="space-y-3 p-5">
                    <p class="text-sm text-slate-600">{{ __('pages.stock_locations.confirm_disable_message') }}</p>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pages.stock_locations.confirm_disable_target') }}</p>
                        <p id="stock-location-delete-label" class="mt-1 font-semibold text-slate-900">—</p>
                    </div>
                    <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                        {{ __('pages.stock_locations.confirm_disable_hint') }}
                    </p>
                    <input type="hidden" id="stock-location-delete-id" value="">
                    <div class="flex justify-end gap-2 border-t border-slate-200 pt-3">
                        <button type="button" data-modal-close="stock-location-delete-modal" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-100">
                            <i data-lucide="x" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.cancel') }}
                        </button>
                        <button type="button" data-action="delete-stock-location" class="rounded-lg border border-red-300 bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700">
                            <i data-lucide="ban" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.disable') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endcan
</div>
</x-layouts.desktop>
