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

        $dados = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $search = (string) ($dados['search'] ?? '');

        $query = User::query()
            ->with(['registers'])
            ->when($search !== '', function ($innerQuery) use ($search) {
                $innerQuery->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name');

        if ($request->filled('page')) {
            $perPage = min(50, max(1, (int) ($dados['per_page'] ?? 10)));
            $paginado = $query->paginate($perPage, ['*'], 'page', max(1, (int) $dados['page']));

            return response()->json([
                'data' => collect($paginado->items())
                    ->map(fn (User $user) => $this->serializarUsuario($user))
                    ->values(),
                'meta' => [
                    'current_page' => $paginado->currentPage(),
                    'last_page' => $paginado->lastPage(),
                    'per_page' => $paginado->perPage(),
                    'total' => $paginado->total(),
                ],
            ]);
        }

        $users = $query->get();

        return response()->json([
            'data' => $users
                ->map(fn (User $user) => $this->serializarUsuario($user))
                ->values(),
        ]);
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
            'registerIds' => ['nullable', 'array'],
            'registerIds.*' => ['uuid', 'exists:registers,id'],
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
        ]);
        $registerIds = $dados['registerIds'] ?? (isset($dados['registerId']) && $dados['registerId'] ? [$dados['registerId']] : []);
        $user->syncAssignedRegisters($registerIds);
        $user->save();
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
            'registerIds' => ['sometimes', 'nullable', 'array'],
            'registerIds.*' => ['uuid', 'exists:registers,id'],
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

        if (array_key_exists('isActive', $dados) && ! $dados['isActive'] && $request->user('api')?->id === $user->id) {
            abort(403, 'Não pode desactivar a sua própria conta enquanto está autenticado.');
        }

        foreach ($mapeamento as $entrada => $coluna) {
            if (array_key_exists($entrada, $dados)) {
                $user->{$coluna} = $dados[$entrada];
            }
        }

        if (! empty($dados['password'])) {
            $user->password = Hash::make($dados['password']);
        }

        $user->save();

        if (array_key_exists('registerIds', $dados)) {
            $user->syncAssignedRegisters($dados['registerIds'] ?? []);
            $user->save();
        } elseif (array_key_exists('registerId', $dados) && $dados['registerId']) {
            $user->syncAssignedRegisters([$dados['registerId']]);
            $user->save();
        }

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

        if ($request->user('api')?->id === $user->id && ! $dados['isActive']) {
            abort(403, 'Não pode desactivar a sua própria conta enquanto está autenticado.');
        }

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

    /** @return array<string, mixed> */
    private function serializarUsuario(User $user): array
    {
        $user->loadMissing(['registers']);

        $registerIds = $user->registers->pluck('id')->all();
        if ($registerIds === [] && $user->register_id) {
            $registerIds = [$user->register_id];
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first() ?? $user->role,
            'isActive' => (bool) $user->is_active,
            'registerId' => $user->register_id,
            'registerIds' => $registerIds,
            'registers' => $user->assignedRegisters()
                ->map(fn ($register) => [
                    'id' => $register->id,
                    'code' => $register->code,
                    'name' => $register->name,
                ])
                ->values()
                ->all(),
            'sourceLocationId' => $user->source_location_id,
            'caixaAtribuido' => $user->caixa_atribuido,
        ];
    }
}
