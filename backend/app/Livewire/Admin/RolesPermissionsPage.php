<?php

namespace App\Livewire\Admin;

use App\Models\User;
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

        $role = Role::query()->where('name', $this->selectedRole)->where('guard_name', 'web')->firstOrFail();
        $permissions = Permission::query()
            ->whereIn('name', $this->rolePermissions)
            ->where('guard_name', 'web')
            ->get();

        $role->syncPermissions($permissions);
        session()->flash('toast', ['type' => 'success', 'message' => 'Permissões da role atualizadas.']);
    }

    public function saveUserAccess(): void
    {
        abort_unless(auth()->user()?->can('roles.manage'), 403);

        $this->validate([
            'selectedUser' => ['required', 'uuid', 'exists:users,id'],
            'selectedUserRole' => ['required', 'in:ADMIN,MANAGER,CASHIER'],
        ]);

        $user = User::query()->findOrFail($this->selectedUser);
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

    public function render()
    {
        return view('livewire.admin.roles-permissions-page')
            ->layout('components.layouts.desktop', ['title' => 'Roles e Permissões | RetailPro'])
            ->with([
                'roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name']),
                'permissions' => Permission::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name']),
                'users' => User::query()->orderBy('name')->get(['id', 'name', 'username', 'role']),
            ]);
    }
}
