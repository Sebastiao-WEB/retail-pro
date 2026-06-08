@php
    $roles_permissions_index_blade_routes = [
'index' => route('roles.permissions'),
        'updateRole' => route('roles.permissions.role', ['role' => '__ROLE__']),
        'updateUser' => route('roles.permissions.user', ['user' => '__ID__']),
        'userPermissions' => route('roles.permissions.user.data', ['user' => '__ID__']),
    ];
@endphp

<x-layouts.desktop :title="__('pages.titles.roles')" admin-page="roles-permissions">
<div
    class="space-y-4"
    data-routes='@json($roles_permissions_index_blade_routes)'
    data-admin-locked="@js($adminLockedPermissions)"
    data-current-user-id='@json($currentUserId)'
>
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('permissions.page_title') }}</p>
        <p class="text-sm text-slate-500">{{ __('permissions.page_subtitle') }}</p>
    </div>

    <section class="rounded-lg border border-slate-200 bg-white p-4">
        <form method="GET" action="{{ route('roles.permissions') }}" data-auto-submit id="role-filter-form" class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('permissions.by_role') }}</p>
                <p class="text-sm text-slate-500">
                    @if ($canManage)
                        {{ __('permissions.by_role_edit') }}
                    @else
                        {{ __('permissions.by_role_view') }}
                    @endif
                </p>
            </div>
            <select name="selectedRole" class="rp-input max-w-xs" @disabled(! $canManage)>
                @foreach ($roles as $role)
                    <option value="{{ $role->name }}" @selected($selectedRole === $role->name)>{{ ($roleLabels[$role->name] ?? $role->name) }} ({{ $role->name }})</option>
                @endforeach
            </select>
            @if ($selectedUser !== '')
                <input type="hidden" name="selectedUser" value="{{ $selectedUser }}">
            @endif
        </form>

        @if ($selectedRole === 'ADMIN')
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                {{ __('permissions.admin_locked_notice') }}
            </div>
        @endif

        <form id="role-permissions-form">
            <div class="space-y-4">
                @foreach ($permissionGroups as $group)
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $group['label'] }}</p>
                        <div class="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($group['permissions'] as $permissionName => $permissionLabel)
                                @php
                                    $isLocked = $selectedRole === 'ADMIN' && in_array($permissionName, $adminLockedPermissions, true);
                                    $isChecked = in_array($permissionName, $rolePermissions, true);
                                @endphp
                                <label @class([
                                    'inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm',
                                    'border-amber-200 bg-amber-50' => $isLocked,
                                    'border-slate-200' => ! $isLocked,
                                    'opacity-60' => ! $canManage,
                                ])>
                                    <input
                                        type="checkbox"
                                        name="rolePermissions[]"
                                        value="{{ $permissionName }}"
                                        @checked($isChecked)
                                        @disabled(! $canManage || $isLocked)
                                        class="h-4 w-4 rounded border-slate-300 text-amber-600"
                                    >
                                    <span>
                                        <span class="block font-medium text-slate-800">{{ $permissionLabel }}</span>
                                        <span class="block text-[11px] text-slate-500">{{ $permissionName }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($canManage)
                <div class="mt-4 flex justify-end border-t border-slate-100 pt-3">
                    <button type="submit" data-action="save-role-permissions" data-role="{{ $selectedRole }}" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                        <i data-lucide="shield-check" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
                        {{ __('permissions.save_role') }}
                    </button>
                </div>
            @endif
        </form>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="mb-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('permissions.by_user') }}</p>
            <p class="text-sm text-slate-500">
                @if ($canManage)
                    {{ __('permissions.by_user_edit') }}
                @else
                    {{ __('permissions.by_user_view') }}
                @endif
            </p>
        </div>

        <form method="GET" action="{{ route('roles.permissions') }}" data-auto-submit id="user-filter-form" class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <input type="hidden" name="selectedRole" value="{{ $selectedRole }}">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('permissions.user_label') }}</label>
                <select name="selectedUser" id="roles-selected-user" class="rp-input">
                    <option value="">{{ __('app.select') }}</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected($selectedUser === $user->id)>{{ $user->name }} ({{ $user->username }})</option>
                    @endforeach
                </select>
            </div>
        </form>

        @if ($selectedUser !== '')
            <form id="user-permissions-form" class="mt-4">
                <div class="mb-4">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('permissions.role_label') }}</label>
                    <select name="selectedUserRole" class="rp-input max-w-xs" @disabled(! $canManage)>
                        @foreach ($roleLabels as $roleCode => $roleLabel)
                            <option value="{{ $roleCode }}" @selected($selectedUserRole === $roleCode)>{{ $roleLabel }} ({{ $roleCode }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-4">
                    @foreach ($permissionGroups as $group)
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $group['label'] }}</p>
                            <div class="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-3">
                                @foreach ($group['permissions'] as $permissionName => $permissionLabel)
                                    <label @class([
                                        'inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm',
                                        'opacity-60' => ! $canManage,
                                    ])>
                                        <input
                                            type="checkbox"
                                            name="userDirectPermissions[]"
                                            value="{{ $permissionName }}"
                                            @checked(in_array($permissionName, $userDirectPermissions, true))
                                            @disabled(! $canManage)
                                            class="h-4 w-4 rounded border-slate-300 text-amber-600"
                                        >
                                        <span>
                                            <span class="block font-medium text-slate-800">{{ $permissionLabel }}</span>
                                            <span class="block text-[11px] text-slate-500">{{ $permissionName }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($canManage)
                    <div class="mt-4 flex justify-end border-t border-slate-100 pt-3">
                        <button type="submit" data-action="save-user-access" data-user-id="{{ $selectedUser }}" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                            <i data-lucide="user-cog" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
                            {{ __('permissions.save_user') }}
                        </button>
                    </div>
                @endif
            </form>
        @else
            <p class="mt-3 text-sm text-slate-500">{{ __('permissions.select_user_hint') }}</p>
        @endif
    </section>
</div>
</x-layouts.desktop>
