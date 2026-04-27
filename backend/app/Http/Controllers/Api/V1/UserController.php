<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeApiUserManagement($request);

        $search = $request->string('search')->toString();
        $users = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->getRoleNames()->first() ?? $user->role,
                    'isActive' => (bool) $user->is_active,
                    'registerId' => $user->register_id,
                    'sourceLocationId' => $user->source_location_id,
                ];
            });

        return response()->json(['data' => $users]);
    }

    public function store(Request $request)
    {
        $this->authorizeApiUserManagement($request);

        $dados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'in:ADMIN,MANAGER,CASHIER'],
            'isActive' => ['nullable', 'boolean'],
            'registerId' => ['nullable', 'uuid', 'exists:registers,id'],
            'sourceLocationId' => ['nullable', 'uuid', 'exists:stock_locations,id'],
        ]);

        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $dados['name'],
            'username' => $dados['username'],
            'email' => $dados['email'],
            'password' => Hash::make($dados['password']),
            'role' => $dados['role'],
            'is_active' => (bool) ($dados['isActive'] ?? true),
            'register_id' => $dados['registerId'] ?? null,
            'source_location_id' => $dados['sourceLocationId'] ?? null,
            'caixa_atribuido' => null,
        ]);
        $user->syncRoles([$dados['role']]);

        return response()->json([
            'message' => 'Utilizador criado com sucesso.',
            'data' => ['id' => $user->id],
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeApiUserManagement($request);

        $dados = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'string', 'max:255', 'unique:users,username,'.$user->id.',id'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$user->id.',id'],
            'password' => ['sometimes', 'nullable', 'string', 'min:6'],
            'role' => ['sometimes', 'in:ADMIN,MANAGER,CASHIER'],
            'isActive' => ['sometimes', 'boolean'],
            'registerId' => ['sometimes', 'nullable', 'uuid', 'exists:registers,id'],
            'sourceLocationId' => ['sometimes', 'nullable', 'uuid', 'exists:stock_locations,id'],
        ]);

        $mapeamento = [
            'name' => 'name',
            'username' => 'username',
            'email' => 'email',
            'role' => 'role',
            'isActive' => 'is_active',
            'registerId' => 'register_id',
            'sourceLocationId' => 'source_location_id',
        ];

        foreach ($mapeamento as $entrada => $coluna) {
            if (array_key_exists($entrada, $dados)) {
                $user->{$coluna} = $dados[$entrada];
            }
        }

        if (! empty($dados['password'])) {
            $user->password = Hash::make($dados['password']);
        }

        $user->save();

        if (array_key_exists('role', $dados)) {
            $user->syncRoles([$dados['role']]);
        }

        return response()->json([
            'message' => 'Utilizador atualizado com sucesso.',
            'data' => ['id' => $user->id],
        ]);
    }

    public function updateStatus(Request $request, User $user)
    {
        $this->authorizeApiUserManagement($request);

        $dados = $request->validate([
            'isActive' => ['required', 'boolean'],
        ]);

        $user->is_active = $dados['isActive'];
        $user->save();

        return response()->json([
            'message' => 'Estado do utilizador atualizado com sucesso.',
            'data' => [
                'id' => $user->id,
                'isActive' => (bool) $user->is_active,
            ],
        ]);
    }

    private function authorizeApiUserManagement(Request $request): void
    {
        $authUser = $request->user('api');
        abort_unless($authUser, 401);

        $allowedByRole = in_array((string) ($authUser->role ?? ''), ['ADMIN', 'MANAGER'], true);
        $allowedByPermission = $authUser->can('users.manage');
        abort_unless($allowedByRole || $allowedByPermission, 403);
    }
}
