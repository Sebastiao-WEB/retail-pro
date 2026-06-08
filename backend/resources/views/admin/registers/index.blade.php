@php
    $registers_index_blade_routes = [
'index' => route('registers.index'),
        'show' => route('registers.show', ['register' => '__ID__']),
        'store' => route('registers.store'),
        'update' => route('registers.update', ['register' => '__ID__']),
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
                    @can('registers.manage')
                        <th class="px-3 py-2">{{ __('app.actions') }}</th>
                    @endcan
                </tr>
            </thead>
            <tbody>
                @forelse ($registers as $register)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 font-medium">{{ $register->code }}</td>
                        <td class="px-3 py-2">{{ $register->name }}</td>
                        <td class="px-3 py-2">
                            @if ($register->sourceLocation)
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                    {{ $register->sourceLocation->code }} — {{ $register->sourceLocation->name }}
                                </span>
                            @else
                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">{{ __('pages.common.no_location_assigned') }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            <span class="{{ $register->is_active ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ $register->is_active ? __('app.active') : __('app.inactive') }}
                            </span>
                        </td>
                        @can('registers.manage')
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <button type="button" data-action="open-edit" data-id="{{ $register->id }}" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">
                                        <i data-lucide="pencil" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.edit') }}
                                    </button>
                                    <button type="button" data-action="confirm-delete" data-id="{{ $register->id }}" class="rounded-md border border-red-200 px-2 py-1 text-xs text-red-600 hover:bg-red-50">
                                        <i data-lucide="power" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.disable') }}
                                    </button>
                                </div>
                            </td>
                        @endcan
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManage ? 5 : 4 }}" class="px-3 py-6 text-center text-slate-500">{{ __('pages.registers.no_registers') }}</td>
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
                    <h3
                        id="register-form-title"
                        class="text-base font-semibold"
                        data-create-title="{{ __('pages.registers.new') }}"
                        data-edit-title="{{ __('pages.registers.edit') }}"
                    >{{ __('pages.registers.new') }}</h3>
                </div>
                <form id="register-form" class="grid grid-cols-1 gap-3 p-5">
                    <input type="hidden" name="editing_id" id="register-editing-id" value="">
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
                        <button type="button" data-modal-close="register-form-modal" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">
                            <i data-lucide="x" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.cancel') }}
                        </button>
                        <button type="submit" data-action="save-register" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black">
                            <i data-lucide="save" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div id="register-delete-modal" class="rp-admin-modal hidden fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4" aria-hidden="true">
            <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
                <h3 class="text-base font-semibold text-slate-900">{{ __('pages.common.confirm_disable_title') }}</h3>
                <p class="mt-2 text-sm text-slate-600">{{ __('pages.registers.confirm_disable_message') }}</p>
                <input type="hidden" id="register-delete-id" value="">
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" data-modal-close="register-delete-modal" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold">
                        <i data-lucide="x" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.close') }}
                    </button>
                    <button type="button" data-action="delete-register" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">
                        <i data-lucide="power" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.disable') }}
                    </button>
                </div>
            </div>
        </div>
    @endcan
</div>
</x-layouts.desktop>
