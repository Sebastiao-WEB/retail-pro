<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.users.title') }}</p>
            <p class="text-sm text-slate-500">{{ __('pages.users.subtitle') }}</p>
        </div>
        @can('users.manage')
            <button type="button" wire:click="openCreateModal" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                <i data-lucide="user-plus" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
                {{ __('pages.users.new') }}
            </button>
        @endcan
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <input wire:model.live.debounce.300ms="search" type="text" class="rp-input" placeholder="{{ __('pages.users.search_placeholder') }}">
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">{{ __('app.fields.name') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.username') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.email') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.profile') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.assigned_registers') }}</th>
                    <th class="px-3 py-2">{{ __('app.fields.stock_location') }}</th>
                    <th class="px-3 py-2">{{ __('app.status') }}</th>
                    @can('users.manage')
                        <th class="px-3 py-2">{{ __('app.actions') }}</th>
                    @endcan
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr wire:key="user-row-{{ $user->id }}" class="border-t border-slate-100">
                        <td class="px-3 py-2 font-medium">{{ $user->name }}</td>
                        <td class="px-3 py-2">{{ $user->username }}</td>
                        <td class="px-3 py-2">{{ $user->email }}</td>
                        <td class="px-3 py-2">{{ $user->getRoleNames()->first() ?? $user->role }}</td>
                        <td class="px-3 py-2">
                            @if ($user->registers->isNotEmpty())
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($user->registers as $register)
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700">{{ $register->code }}</span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-xs text-slate-600">
                            @if ($user->sourceLocation)
                                {{ $user->sourceLocation->code }}
                            @else
                                <span class="text-amber-700">{{ __('pages.common.not_defined') }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            <span class="{{ $user->is_active ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ $user->is_active ? __('app.active') : __('app.inactive') }}
                            </span>
                        </td>
                        @can('users.manage')
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <button type="button" wire:click="openEditModal('{{ $user->id }}')" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50"><i data-lucide="pencil" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.edit') }}</button>
                                    @if ($user->id !== auth()->id())
                                        <button type="button" wire:click="confirmDisable('{{ $user->id }}')" class="rounded-md border border-red-200 px-2 py-1 text-xs text-red-600 hover:bg-red-50"><i data-lucide="user-x" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('pages.users.disable') }}</button>
                                    @else
                                        <span class="text-xs text-slate-400" title="{{ __('pages.common.current_account_hint') }}">{{ __('pages.common.current_account') }}</span>
                                    @endif
                                </div>
                            </td>
                        @endcan
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ auth()->user()?->can('users.manage') ? 8 : 7 }}" class="px-3 py-6 text-center text-slate-500">{{ __('pages.users.no_users') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $users->links() }}</div>

    @can('users.manage')
        @if ($modalOpen)
            <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4">
                <div class="w-full max-w-2xl rounded-xl bg-white shadow-xl">
                    <div class="border-b border-slate-200 px-5 py-3">
                        <h3 class="text-base font-semibold">{{ $editingId ? __('pages.users.edit') : __('pages.users.new') }}</h3>
                    </div>
                    <div class="grid grid-cols-1 gap-3 p-5 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.name') }}</label>
                            <input wire:model.defer="name" type="text" class="rp-input">
                            @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.username') }}</label>
                            <input wire:model.defer="username" type="text" class="rp-input">
                            @error('username') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.email') }}</label>
                            <input wire:model.defer="email" type="email" class="rp-input">
                            @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.profile') }}</label>
                            <select wire:model.defer="role" class="rp-input">
                                <option value="ADMIN">{{ __('app.roles.ADMIN') }}</option>
                                <option value="MANAGER">{{ __('app.roles.MANAGER') }}</option>
                                <option value="CASHIER">{{ __('app.roles.CASHIER') }}</option>
                            </select>
                            @error('role') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.users.registers_pos') }}</label>
                            <p class="mb-2 text-[11px] text-slate-500">{{ __('pages.users.registers_hint') }}</p>
                            <div class="max-h-36 space-y-2 overflow-y-auto rounded-lg border border-slate-200 p-3">
                                @forelse ($registers as $register)
                                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-slate-100 px-2 py-1.5">
                                        <label class="flex flex-1 items-center gap-2 text-sm text-slate-700">
                                            <input type="checkbox" wire:model.live="register_ids" value="{{ $register->id }}" class="h-4 w-4 accent-amber-500">
                                            <span>{{ $register->code }} — {{ $register->name }}</span>
                                        </label>
                                        @if ($register->sourceLocation)
                                            <button
                                                type="button"
                                                wire:click="aplicarLocalizacaoDoCaixa('{{ $register->id }}')"
                                                class="rounded border border-slate-200 px-2 py-0.5 text-[10px] font-semibold text-slate-600 hover:bg-slate-50"
                                                title="{{ __('pages.common.use_register_location') }}"
                                            >
                                                {{ $register->sourceLocation->code }}
                                            </button>
                                        @else
                                            <span class="text-[10px] text-red-600">{{ __('pages.common.no_location') }}</span>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-xs text-slate-500">{{ __('pages.common.no_active_registers') }}</p>
                                @endforelse
                            </div>
                            @error('register_ids') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.users.default_stock_location') }}</label>
                            <p class="mb-2 text-[11px] text-slate-500">{{ __('pages.users.default_stock_location_hint') }}</p>
                            <select wire:model.defer="source_location_id" class="rp-input">
                                <option value="">{{ __('pages.common.no_link_register_login') }}</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}">{{ $location->code }} - {{ $location->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.common.password') }} {{ $editingId ? __('pages.common.password_change_hint') : '' }}</label>
                            <input wire:model.defer="password" type="password" class="rp-input">
                            @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                            <input wire:model.defer="is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-amber-600" @disabled($editingId && $editingId === auth()->id())>
                            {{ __('app.active') }}
                        </label>
                        @if ($editingId && $editingId === auth()->id())
                            <p class="text-xs text-slate-500">{{ __('pages.common.current_account_active_hint') }}</p>
                        @endif
                        @error('is_active') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                        <button type="button" wire:click="closeModal" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600"><i data-lucide="x" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.cancel') }}</button>
                        <button type="button" wire:click="save" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black"><i data-lucide="save" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.save') }}</button>
                    </div>
                </div>
            </div>
        @endif

        @if ($confirmDisableOpen)
            <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/45 p-4">
                <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
                    <h3 class="text-base font-semibold text-slate-900">{{ __('pages.common.confirm_disable_title') }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ __('pages.users.confirm_disable_message') }}</p>
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" wire:click="$set('confirmDisableOpen', false)" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold"><i data-lucide="x" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.close') }}</button>
                        <button type="button" wire:click="disable" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600"><i data-lucide="user-x" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('pages.users.disable') }}</button>
                    </div>
                </div>
            </div>
        @endif
    @endcan
</div>
