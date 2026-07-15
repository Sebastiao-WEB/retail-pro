@php
    $registers_index_blade_routes = [
        'index' => route('registers.index'),
        'store' => route('registers.store'),
        'destroy' => route('registers.destroy', ['register' => '__ID__']),
    ];
@endphp

<x-layouts.desktop :title="__('pages.titles.registers')" admin-page="registers">
<div
    class="space-y-4"
    data-routes='@json($registers_index_blade_routes)'
>
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.registers.title') }}</p>
            <p class="text-sm text-slate-500">{{ __('pages.registers.subtitle') }}</p>
        </div>
        @can('registers.manage')
            <button type="button" data-action="open-create" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                <i data-lucide="plus" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('pages.registers.new') }}
            </button>
        @endcan
    </div>

    <form method="GET" action="{{ route('registers.index') }}" data-auto-submit data-debounce="300" class="rounded-lg border border-slate-200 bg-white p-4">
        <input name="search" type="text" value="{{ $search }}" class="rp-input" placeholder="{{ __('pages.registers.search_placeholder') }}">
    </form>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">{{ __('app.fields.code') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.name') }}</th>
                    <th class="px-3 py-2">{{ __('pages.registers.stock_location') }}</th>
                    <th class="px-3 py-2">{{ __('app.status') }}</th>
                    <th class="px-3 py-2">{{ __('app.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($registers as $register)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 font-medium">{{ $register->code }}</td>
                        <td class="px-3 py-2">{{ $register->name }}</td>
                        <td class="px-3 py-2">
                            @if ($register->stockLocations->isNotEmpty())
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($register->stockLocations->sortBy('code') as $stockLocation)
                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                            {{ $stockLocation->code }} — {{ $stockLocation->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">{{ __('pages.common.no_location_assigned') }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            <span class="{{ $register->is_active ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ $register->is_active ? __('app.active') : __('app.inactive') }}
                            </span>
                        </td>
                        <td class="px-3 py-2">
                            @can('registers.manage')
                                <div class="flex flex-wrap items-center gap-1">
                                    <a
                                        href="{{ route('registers.edit', ['register' => $register, 'search' => $search]) }}"
                                        data-rp-page-nav
                                        title="{{ __('app.edit') }}"
                                        aria-label="{{ __('app.edit') }}: {{ $register->name }}"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 text-slate-700 hover:bg-slate-50"
                                    >
                                        <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                                    </a>
                                    @if ($register->is_active)
                                        <button
                                            type="button"
                                            data-action="confirm-delete"
                                            data-id="{{ $register->id }}"
                                            data-code="{{ $register->code }}"
                                            data-name="{{ $register->name }}"
                                            data-modal-target="register-delete-modal"
                                            data-input-target="register-delete-id"
                                            data-label-target="register-delete-label"
                                            title="{{ __('app.disable') }}"
                                            aria-label="{{ __('app.disable') }}: {{ $register->name }}"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-red-200 text-red-600 hover:bg-red-50"
                                        >
                                            <i data-lucide="ban" class="h-3.5 w-3.5"></i>
                                        </button>
                                    @endif
                                </div>
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-6 text-center text-slate-500">{{ __('pages.registers.no_registers') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $registers->links() }}</div>

    @can('registers.manage')
        <div id="register-form-modal" class="rp-admin-modal hidden fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4" aria-hidden="true">
            <div class="w-full max-w-lg rounded-xl bg-white shadow-xl">
                <div class="border-b border-slate-200 px-5 py-3">
                    <h3 class="text-base font-semibold text-slate-900">{{ __('pages.registers.new') }}</h3>
                </div>
                <form id="register-form" class="grid grid-cols-1 gap-3 p-5">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.code') }}</label>
                        <input name="code" type="text" class="rp-input" placeholder="{{ __('pages.registers.code_placeholder') }}">
                        <p data-field-error="code" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.name') }}</label>
                        <input name="name" type="text" class="rp-input" placeholder="{{ __('pages.registers.name_placeholder') }}">
                        <p data-field-error="name" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                        <input name="is_active" type="checkbox" value="1" checked class="h-4 w-4 rounded border-slate-300 text-amber-600">
                        {{ __('app.active') }}
                    </label>
                    <div class="flex justify-end gap-2 border-t border-slate-200 pt-3">
                        <button type="button" data-modal-close="register-form-modal" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-100">
                            <i data-lucide="x" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.cancel') }}
                        </button>
                        <button type="submit" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                            <i data-lucide="save" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div id="register-delete-modal" class="rp-admin-modal hidden fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="register-delete-title">
            <div class="w-full max-w-md rounded-xl bg-white shadow-xl">
                <div class="border-b border-slate-200 px-5 py-3">
                    <h3 id="register-delete-title" class="text-base font-semibold text-slate-900">{{ __('pages.common.confirm_disable_title') }}</h3>
                </div>
                <div class="space-y-3 p-5">
                    <p class="text-sm text-slate-600">{{ __('pages.registers.confirm_disable_message') }}</p>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pages.registers.confirm_disable_target') }}</p>
                        <p id="register-delete-label" class="mt-1 font-semibold text-slate-900">—</p>
                    </div>
                    <input type="hidden" id="register-delete-id" value="">
                    <div class="flex justify-end gap-2 border-t border-slate-200 pt-3">
                        <button type="button" data-modal-close="register-delete-modal" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-100">
                            <i data-lucide="x" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.cancel') }}
                        </button>
                        <button type="button" data-action="delete-register" class="rounded-lg border border-red-300 bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700">
                            <i data-lucide="ban" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.disable') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endcan
</div>
</x-layouts.desktop>
