@php
    $userRole = old('role', $user->getRoleNames()->first() ?? $user->role ?? 'MANAGER');
    $selectedRegisterIds = old('register_ids', $user->registers->pluck('id')->all());
    $isSelf = $user->id === $currentUserId;
@endphp

<x-layouts.desktop :title="__('pages.titles.users_edit')" admin-page="users-edit">
<div class="mx-auto max-w-3xl space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('pages.users.edit') }}</p>
            <p class="text-sm text-slate-500">{{ $user->name }} · {{ $user->username }}</p>
        </div>
        <a href="{{ route('users.index') }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
            <i data-lucide="arrow-left" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.back') }}
        </a>
    </div>

    <form
        id="user-edit-form"
        method="POST"
        action="{{ route('users.update', $user) }}"
        class="grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-5 md:grid-cols-2"
    >
        @csrf
        @method('PUT')

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.name') }}</label>
            <input name="name" type="text" value="{{ old('name', $user->name) }}" class="rp-input @error('name') border-red-300 @enderror">
            @error('name')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.username') }}</label>
            <input name="username" type="text" value="{{ old('username', $user->username) }}" class="rp-input @error('username') border-red-300 @enderror">
            @error('username')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.email') }}</label>
            <input name="email" type="email" value="{{ old('email', $user->email) }}" class="rp-input @error('email') border-red-300 @enderror">
            @error('email')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('app.fields.profile') }}</label>
            <select name="role" class="rp-input @error('role') border-red-300 @enderror">
                <option value="ADMIN" @selected($userRole === 'ADMIN')>{{ __('app.roles.ADMIN') }}</option>
                <option value="MANAGER" @selected($userRole === 'MANAGER')>{{ __('app.roles.MANAGER') }}</option>
                <option value="CASHIER" @selected($userRole === 'CASHIER')>{{ __('app.roles.CASHIER') }}</option>
            </select>
            @error('role')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="md:col-span-2">
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.users.registers_pos') }}</label>
            <p class="mb-2 text-[11px] text-slate-500">{{ __('pages.users.registers_hint') }}</p>
            <div class="max-h-36 space-y-2 overflow-y-auto rounded-lg border border-slate-200 p-3">
                @forelse ($registers as $register)
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-slate-100 px-2 py-1.5"
                        data-register-row
                        data-location-id="{{ $register->sourceLocation?->id }}"
                    >
                        <label class="flex flex-1 items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                name="register_ids[]"
                                value="{{ $register->id }}"
                                @checked(in_array($register->id, $selectedRegisterIds, true))
                                class="h-4 w-4 accent-amber-500"
                            >
                            <span>{{ $register->code }} — {{ $register->name }}</span>
                        </label>
                        @if ($register->sourceLocation)
                            <button
                                type="button"
                                data-action="apply-register-location"
                                data-location-id="{{ $register->sourceLocation->id }}"
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
            @error('register_ids')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
            @error('register_ids.*')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="md:col-span-2">
            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('pages.users.default_stock_location') }}</label>
            <p class="mb-2 text-[11px] text-slate-500">{{ __('pages.users.default_stock_location_hint') }}</p>
            <select name="source_location_id" class="rp-input @error('source_location_id') border-red-300 @enderror">
                <option value="">{{ __('pages.common.no_link_register_login') }}</option>
                @foreach ($locations as $location)
                    <option value="{{ $location->id }}" @selected((string) old('source_location_id', $user->source_location_id) === (string) $location->id)>
                        {{ $location->code }} - {{ $location->name }}
                    </option>
                @endforeach
            </select>
            @error('source_location_id')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="md:col-span-2">
            <label class="mb-1 block text-xs font-semibold text-slate-600">
                {{ __('pages.common.password') }}
                <span class="font-normal text-slate-500">{{ __('pages.common.password_change_hint') }}</span>
            </label>
            <input name="password" type="password" class="rp-input @error('password') border-red-300 @enderror">
            @error('password')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="md:col-span-2">
            @if ($isSelf)
                <input type="hidden" name="is_active" value="1">
                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" value="1" checked disabled class="h-4 w-4 rounded border-slate-300 text-amber-600">
                    {{ __('app.active') }}
                </label>
                <p class="mt-1 text-xs text-slate-500">{{ __('pages.common.current_account_active_hint') }}</p>
            @else
                <input type="hidden" name="is_active" value="0">
                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                    <input
                        name="is_active"
                        type="checkbox"
                        value="1"
                        @checked(old('is_active', $user->is_active ? '1' : '0') == '1')
                        class="h-4 w-4 rounded border-slate-300 text-amber-600"
                    >
                    {{ __('app.active') }}
                </label>
            @endif
            @error('is_active')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="md:col-span-2 flex justify-end gap-2 border-t border-slate-200 pt-3">
            <a href="{{ route('users.index') }}" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">
                <i data-lucide="x" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.cancel') }}
            </a>
            <button type="submit" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black">
                <i data-lucide="save" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>{{ __('app.save') }}
            </button>
        </div>
    </form>
</div>
</x-layouts.desktop>
