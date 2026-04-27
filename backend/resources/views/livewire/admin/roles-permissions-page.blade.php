<div class="space-y-4">
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Gestão de roles e permissões</p>
        <p class="text-sm text-slate-500">Controle central de acessos por perfil e por utilizador.</p>
    </div>

    @can('roles.manage')
        <section class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Permissões por role</p>
                    <p class="text-sm text-slate-500">Selecione a role e marque as permissões.</p>
                </div>
                <select wire:model.live="selectedRole" class="rp-input max-w-xs">
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}">{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($permissions as $permission)
                    <label class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm">
                        <input type="checkbox" value="{{ $permission->name }}" wire:model.defer="rolePermissions" class="h-4 w-4 rounded border-slate-300 text-amber-600">
                        <span>{{ $permission->name }}</span>
                    </label>
                @endforeach
            </div>

            <div class="mt-4 flex justify-end">
                <button type="button" wire:click="saveRolePermissions" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                    <i data-lucide="shield-check" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
                    Guardar permissões da role
                </button>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="mb-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Acesso por utilizador</p>
                <p class="text-sm text-slate-500">Ajuste role e permissões diretas do utilizador.</p>
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
                    <select wire:model.defer="selectedUserRole" class="rp-input">
                        <option value="ADMIN">ADMIN</option>
                        <option value="MANAGER">MANAGER</option>
                        <option value="CASHIER">CASHIER</option>
                    </select>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($permissions as $permission)
                    <label class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm">
                        <input type="checkbox" value="{{ $permission->name }}" wire:model.defer="userDirectPermissions" class="h-4 w-4 rounded border-slate-300 text-amber-600">
                        <span>{{ $permission->name }}</span>
                    </label>
                @endforeach
            </div>

            <div class="mt-4 flex justify-end">
                <button type="button" wire:click="saveUserAccess" class="rounded-lg bg-[var(--gold)] px-3 py-2 text-xs font-semibold text-black hover:brightness-95">
                    <i data-lucide="user-cog" class="mr-1 inline-block h-3.5 w-3.5 align-[-2px]"></i>
                    Guardar acesso do utilizador
                </button>
            </div>
        </section>
    @else
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
            Não possui permissão para gerir roles e permissões.
        </div>
    @endcan
</div>
