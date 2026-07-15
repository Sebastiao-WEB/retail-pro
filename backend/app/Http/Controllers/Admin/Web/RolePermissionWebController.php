<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Web\Concerns\AuthorizesAdminWeb;
use App\Http\Controllers\Admin\Web\Concerns\RespondsAsJson;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionWebController extends Controller
{
    use AuthorizesAdminWeb;
    use RespondsAsJson;

    public function index(Request $request)
    {
        $this->authorizeAdmin('roles.view');

        $selectedRole = $request->string('selectedRole')->toString() ?: 'MANAGER';
        $selectedUser = $request->string('selectedUser')->toString();

        $role = Role::query()->where('name', $selectedRole)->where('guard_name', 'web')->first();
        $rolePermissions = $role
            ? $role->permissions()->pluck('name')->values()->all()
            : [];

        $selectedUserRole = 'CASHIER';
        $userDirectPermissions = [];
        if ($selectedUser !== '') {
            $user = User::query()->find($selectedUser);
            if ($user) {
                $selectedUserRole = $user->getRoleNames()->first() ?? ($user->role ?: 'CASHIER');
                $userDirectPermissions = $user->getDirectPermissions()->pluck('name')->values()->all();
            }
        }

        return view('admin.roles-permissions.index', [
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name']),
            'permissionGroups' => PermissionCatalog::groups(),
            'roleLabels' => $this->roleLabels(),
            'adminLockedPermissions' => PermissionCatalog::adminLockedPermissions(),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'username', 'role']),
            'canManage' => auth()->user()?->can('roles.manage') ?? false,
            'selectedRole' => $selectedRole,
            'rolePermissions' => $rolePermissions,
            'selectedUser' => $selectedUser,
            'selectedUserRole' => $selectedUserRole,
            'userDirectPermissions' => $userDirectPermissions,
            'currentUserId' => auth()->id(),
        ]);
    }

    public function updateRole(Request $request, string $role)
    {
        $this->authorizeAdmin('roles.manage');

        try {
            $dados = $request->validate([
                'rolePermissions' => ['nullable', 'array'],
                'rolePermissions.*' => ['string'],
            ]);
        } catch (ValidationException $exception) {
            return $this->jsonFromValidation($exception);
        }

        $permissionNames = $dados['rolePermissions'] ?? [];

        if ($role === 'ADMIN') {
            $permissionNames = array_values(array_unique(array_merge(
                $permissionNames,
                PermissionCatalog::adminLockedPermissions()
            )));
        }

        $roleModel = Role::query()->where('name', $role)->where('guard_name', 'web')->firstOrFail();
        $permissions = Permission::query()
            ->whereIn('name', $permissionNames)
            ->where('guard_name', 'web')
            ->get();

        $roleModel->syncPermissions($permissions);

        return $this->jsonOk(null, __('toasts.role_permissions_saved'));
    }

    public function userPermissions(User $user)
    {
        $this->authorizeAdmin('roles.manage');

        return $this->jsonOk([
            'role' => $user->getRoleNames()->first() ?? ($user->role ?: 'CASHIER'),
            'permissions' => $user->getDirectPermissions()->pluck('name')->values()->all(),
        ]);
    }

    public function updateUser(Request $request, User $user)
    {
        $this->authorizeAdmin('roles.manage');

        try {
            $dados = $request->validate([
                'selectedUserRole' => ['required', 'in:ADMIN,MANAGER,CASHIER'],
                'userDirectPermissions' => ['nullable', 'array'],
                'userDirectPermissions.*' => ['string'],
            ]);
        } catch (ValidationException $exception) {
            return $this->jsonFromValidation($exception);
        }

        $authUser = auth()->user();
        abort_if($user->id === $authUser?->id, 403, 'Não pode alterar os seus próprios acessos nesta página.');

        if ($dados['selectedUserRole'] === 'ADMIN' && ! $authUser?->hasRole('ADMIN')) {
            abort(403, 'Apenas administradores podem atribuir a role ADMIN.');
        }

        if ($user->hasRole('ADMIN') && ! $authUser?->hasRole('ADMIN')) {
            abort(403, 'Não pode alterar acessos de outro administrador.');
        }

        $user->syncRoles([$dados['selectedUserRole']]);
        $user->role = $dados['selectedUserRole'];
        $user->save();

        $permissions = Permission::query()
            ->whereIn('name', $dados['userDirectPermissions'] ?? [])
            ->where('guard_name', 'web')
            ->get();
        $user->syncPermissions($permissions);

        return $this->jsonOk(null, __('toasts.user_access_saved'));
    }

    /** @return array<string, string> */
    private function roleLabels(): array
    {
        return [
            'ADMIN' => __('app.roles.ADMIN'),
            'MANAGER' => __('app.roles.MANAGER'),
            'CASHIER' => __('app.roles.CASHIER'),
        ];
    }
}
