<div class="space-y-4">
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Gestão de roles e permissões</p>
        <p class="text-sm text-slate-500">Controle central de acessos por perfil e por utilizador.</p>
    </div>

    <section class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Permissões por role</p>
                <p class="text-sm text-slate-500">
                    @if ($canManage)
                        Selecione a role e marque as permissões.
                    @else
                        Visualização das permissões atribuídas a cada role.
                    @endif
                </p>
            </div>
            <select wire:model.live="selectedRole" class="rp-input max-w-xs" @disabled(! $canManage)>
                @foreach ($roles as $role)
                    <option value="{{ $role->name }}">{{ $roleLabels[$role->name] ?? $role->name }} ({{ $role->name }})</option>
                @endforeach
            </select>
        </div>

        @if ($selectedRole === 'ADMIN')
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                A role Administrador mantém sempre as permissões críticas de sistema (utilizadores, roles e configurações).
            </div>
        @endif

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
                                    value="{{ $permissionName }}"
                                    wire:model.defer="rolePermissions"
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
                <button type="button" wire:click="saveRolePermissions" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                    <i data-lucide="shield-check" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
                    Guardar permissões da role
                </button>
            </div>
        @endif
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="mb-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Acesso por utilizador</p>
            <p class="text-sm text-slate-500">
                @if ($canManage)
                    Ajuste a role e permissões directas do utilizador (excepto a sua própria conta).
                @else
                    Consulta da role e permissões directas de cada utilizador.
                @endif
            </p>
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Utilizador</label>
                <select wire:model.live="selectedUser" class="rp-input">
                    <option value="">Selecione...</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->username }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Role</label>
                <select wire:model.defer="selectedUserRole" class="rp-input" @disabled(! $canManage || $selectedUser === '')>
                    <option value="ADMIN">Administrador (ADMIN)</option>
                    <option value="MANAGER">Gestor (MANAGER)</option>
                    <option value="CASHIER">Caixa (CASHIER)</option>
                </select>
            </div>
        </div>

        @if ($selectedUser !== '')
            <div class="mt-4 space-y-4">
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
                                        value="{{ $permissionName }}"
                                        wire:model.defer="userDirectPermissions"
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
                    <button type="button" wire:click="saveUserAccess" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                        <i data-lucide="user-cog" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
                        Guardar acesso do utilizador
                    </button>
                </div>
            @endif
        @else
            <p class="mt-3 text-sm text-slate-500">Seleccione um utilizador para ver ou editar os acessos.</p>
        @endif
    </section>
</div>
