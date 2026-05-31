<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Support\PermissionCatalog;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesPermissionsPage extends Component
{
    public string $selectedRole = 'MANAGER';

    public array $rolePermissions = [];

    public string $selectedUser = '';

    public string $selectedUserRole = 'CASHIER';

    public array $userDirectPermissions = [];

    public function mount(): void
    {
        $this->loadRolePermissions();
    }

    public function updatedSelectedRole(): void
    {
        $this->loadRolePermissions();
    }

    public function updatedSelectedUser(string $value): void
    {
        if ($value === '') {
            $this->selectedUserRole = 'CASHIER';
            $this->userDirectPermissions = [];

            return;
        }

        $user = User::query()->find($value);
        if (! $user) {
            return;
        }

        $this->selectedUserRole = $user->getRoleNames()->first() ?? ($user->role ?: 'CASHIER');
        $this->userDirectPermissions = $user->getDirectPermissions()->pluck('name')->values()->all();
    }

    public function saveRolePermissions(): void
    {
        abort_unless(auth()->user()?->can('roles.manage'), 403);

        $permissionNames = $this->rolePermissions;

        if ($this->selectedRole === 'ADMIN') {
            $permissionNames = array_values(array_unique(array_merge(
                $permissionNames,
                PermissionCatalog::adminLockedPermissions()
            )));
        }

        $role = Role::query()->where('name', $this->selectedRole)->where('guard_name', 'web')->firstOrFail();
        $permissions = Permission::query()
            ->whereIn('name', $permissionNames)
            ->where('guard_name', 'web')
            ->get();

        $role->syncPermissions($permissions);
        $this->loadRolePermissions();

        session()->flash('toast', ['type' => 'success', 'message' => 'Permissões da role atualizadas.']);
    }

    public function saveUserAccess(): void
    {
        abort_unless(auth()->user()?->can('roles.manage'), 403);

        $this->validate([
            'selectedUser' => ['required', 'uuid', 'exists:users,id'],
            'selectedUserRole' => ['required', 'in:ADMIN,MANAGER,CASHIER'],
        ]);

        $authUser = auth()->user();
        abort_if($this->selectedUser === $authUser?->id, 403, 'Não pode alterar os seus próprios acessos nesta página.');

        if ($this->selectedUserRole === 'ADMIN' && ! $authUser?->hasRole('ADMIN')) {
            abort(403, 'Apenas administradores podem atribuir a role ADMIN.');
        }

        $user = User::query()->findOrFail($this->selectedUser);

        if ($user->hasRole('ADMIN') && ! $authUser?->hasRole('ADMIN')) {
            abort(403, 'Não pode alterar acessos de outro administrador.');
        }

        $user->syncRoles([$this->selectedUserRole]);
        $user->role = $this->selectedUserRole;
        $user->save();

        $permissions = Permission::query()
            ->whereIn('name', $this->userDirectPermissions)
            ->where('guard_name', 'web')
            ->get();
        $user->syncPermissions($permissions);

        session()->flash('toast', ['type' => 'success', 'message' => 'Acessos do utilizador atualizados.']);
    }

    private function loadRolePermissions(): void
    {
        $role = Role::query()->where('name', $this->selectedRole)->where('guard_name', 'web')->first();
        $this->rolePermissions = $role
            ? $role->permissions()->pluck('name')->values()->all()
            : [];
    }

    /** @return array<string, string> */
    private function roleLabels(): array
    {
        return [
            'ADMIN' => 'Administrador',
            'MANAGER' => 'Gestor',
            'CASHIER' => 'Caixa',
        ];
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('roles.view'), 403);

        return view('livewire.admin.roles-permissions-page')
            ->layout('components.layouts.desktop', ['title' => 'Roles e Permissões | RetailPro'])
            ->with([
                'roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name']),
                'permissionGroups' => PermissionCatalog::groups(),
                'roleLabels' => $this->roleLabels(),
                'adminLockedPermissions' => PermissionCatalog::adminLockedPermissions(),
                'users' => User::query()->orderBy('name')->get(['id', 'name', 'username', 'role']),
                'canManage' => auth()->user()?->can('roles.manage') ?? false,
            ]);
    }
}
