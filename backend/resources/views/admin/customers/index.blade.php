@php
    $customers_index_blade_routes = [
'index' => route('customers.index'),
        'show' => route('customers.show', ['customer' => '__ID__']),
        'store' => route('customers.store'),
        'update' => route('customers.update', ['customer' => '__ID__']),
        'destroy' => route('customers.destroy', ['customer' => '__ID__']),
    ];
@endphp

<x-layouts.desktop :title="__('pages.titles.customers')" admin-page="customers">
<div
    class="space-y-4"
    data-routes='@json($customers_index_blade_routes)'
>
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.customers.title') }}</p>
            <p class="text-sm text-slate-500">{{ __('pages.customers.subtitle') }}</p>
        </div>
        @can('customers.manage')
            <button type="button" data-action="open-create" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                <i data-lucide="user-plus" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
                {{ __('pages.customers.new') }}
            </button>
        @endcan
    </div>

    <form method="GET" action="{{ route('customers.index') }}" data-auto-submit data-debounce="300" class="rounded-lg border border-slate-200 bg-white p-4">
        <input name="search" type="text" value="{{ $search }}" class="rp-input" placeholder="{{ __('pages.customers.search_placeholder') }}">
    </form>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">{{ __('app.fields.name') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.phone') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.email') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.nuit') }}</th>
                    <th class="px-3 py-2">{{ __('app.status') }}</th>
                    @can('customers.manage')
                        <th class="px-3 py-2">{{ __('app.actions') }}</th>
                    @endcan
                </tr>
            </thead>
            <tbody>
                @forelse ($clientes as $cliente)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 font-medium">{{ $cliente->nome }}</td>
                        <td class="px-3 py-2">{{ $cliente->telefone ?: '---' }}</td>
                        <td class="px-3 py-2">{{ $cliente->email ?: '---' }}</td>
                        <td class="px-3 py-2">{{ $cliente->nuit ?: '---' }}</td>
                        <td class="px-3 py-2">
                            <span class="{{ $cliente->is_active ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ $cliente->is_active ? __('app.active') : __('app.inactive') }}
                            </span>
                        </td>
                        @can('customers.manage')
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <button type="button" data-action="open-edit" data-id="{{ $cliente->id }}" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">
                                        <i data-lucide="pencil" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.edit') }}
                                    </button>
                                    <button type="button" data-action="confirm-delete" data-id="{{ $cliente->id }}" class="rounded-md border border-red-200 px-2 py-1 text-xs text-red-600 hover:bg-red-50">
                                        <i data-lucide="user-x" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.disable') }}
                                    </button>
                                </div>
                            </td>
                        @endcan
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManage ? 6 : 5 }}" class="px-3 py-6 text-center text-slate-500">{{ __('pages.customers.no_customers') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $clientes->links() }}</div>

    @can('customers.manage')
        <div id="customer-form-modal" class="rp-admin-modal hidden fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4" aria-hidden="true">
            <div class="w-full max-w-2xl rounded-xl bg-white shadow-xl">
                <div class="border-b border-slate-200 px-5 py-3">
                    <h3
                        id="customer-form-title"
                        class="text-base font-semibold text-slate-900"
                        data-create-title="{{ __('pages.customers.new') }}"
                        data-edit-title="{{ __('pages.customers.edit') }}"
                    >{{ __('pages.customers.new') }}</h3>
                </div>
                <form id="customer-form" class="grid grid-cols-1 gap-3 p-5 md:grid-cols-2">
                    <input type="hidden" name="editing_id" id="customer-editing-id" value="">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.name') }}</label>
                        <input name="nome" type="text" class="rp-input">
                        <p data-field-error="nome" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.phone') }}</label>
                        <input name="telefone" type="text" class="rp-input">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.email') }}</label>
                        <input name="email" type="email" class="rp-input">
                        <p data-field-error="email" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.nuit') }}</label>
                        <input name="nuit" type="text" class="rp-input">
                    </div>
                    <div class="flex items-center gap-2 pt-6">
                        <input id="cliente-ativo" name="is_active" type="checkbox" value="1" checked class="h-4 w-4 rounded border-slate-300 text-amber-600">
                        <label for="cliente-ativo" class="text-sm text-slate-600">{{ __('app.active') }}</label>
                    </div>
                    <div class="md:col-span-2 flex justify-end gap-2 border-t border-slate-200 pt-3">
                        <button type="button" data-modal-close="customer-form-modal" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-100">
                            <i data-lucide="x" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.cancel') }}
                        </button>
                        <button type="submit" data-action="save-customer" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                            <i data-lucide="save" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div id="customer-delete-modal" class="rp-admin-modal hidden fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4" aria-hidden="true">
            <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
                <h3 class="text-base font-semibold text-slate-900">{{ __('pages.common.confirm_disable_title') }}</h3>
                <p class="mt-2 text-sm text-slate-600">{{ __('pages.customers.confirm_disable_message') }}</p>
                <input type="hidden" id="customer-delete-id" value="">
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" data-modal-close="customer-delete-modal" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-50">
                        <i data-lucide="x" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.close') }}
                    </button>
                    <button type="button" data-action="delete-customer" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-100">
                        <i data-lucide="user-x" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.disable') }}
                    </button>
                </div>
            </div>
        </div>
    @endcan
</div>
</x-layouts.desktop>
