<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Web\Concerns\AuthorizesAdminWeb;
use App\Http\Controllers\Admin\Web\Concerns\RespondsAsJson;
use App\Models\Register;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserWebController extends Controller
{
    use AuthorizesAdminWeb;
    use RespondsAsJson;

    public function index(Request $request)
    {
        $this->authorizeAdmin('users.view');

        $search = $request->string('search')->toString();

        $users = User::query()
            ->with(['registers', 'sourceLocation'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
            'canManage' => auth()->user()?->can('users.manage') ?? false,
            'currentUserId' => auth()->id(),
            'registers' => Register::query()->with('sourceLocation')->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'locations' => StockLocation::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'register_id']),
        ]);
    }

    public function edit(User $user)
    {
        $this->authorizeAdmin('users.manage');

        $user->load(['registers', 'sourceLocation']);

        return view('admin.users.edit', [
            'user' => $user,
            'currentUserId' => auth()->id(),
            'registers' => Register::query()->with('sourceLocation')->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'locations' => StockLocation::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'register_id']),
        ]);
    }

    public function show(User $user)
    {
        $this->authorizeAdmin('users.manage');

        $user->load('registers');

        return $this->jsonOk($this->serializeUser($user));
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin('users.manage');

        try {
            $payload = $this->validatedPayload($request);
            $payload['id'] = (string) Str::uuid();
            $payload['password'] = Hash::make($payload['password']);
            $registerIds = $payload['register_ids'] ?? [];
            $sourceLocationId = $payload['source_location_id'] ?? null;
            unset($payload['register_ids']);

            $user = User::query()->create($payload);
            $user->syncAssignedRegisters($registerIds);
            $this->applySourceLocation($user, $sourceLocationId);
            $user->save();
            $user->syncRoles([$payload['role']]);
        } catch (ValidationException $exception) {
            return $this->jsonFromValidation($exception);
        }

        return $this->jsonOk($this->serializeUser($user->fresh(['registers'])), __('toasts.user_created'), 201);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeAdmin('users.manage');

        try {
            $payload = $this->validatedPayload($request, $user->id);

            if ($user->id === auth()->id() && ! ($payload['is_active'] ?? true)) {
                throw ValidationException::withMessages([
                    'is_active' => ['Não pode desactivar a sua própria conta enquanto está autenticado.'],
                ]);
            }

            $registerIds = $payload['register_ids'] ?? [];
            $sourceLocationId = $payload['source_location_id'] ?? null;
            unset($payload['register_ids']);

            if (! empty($payload['password'])) {
                $payload['password'] = Hash::make($payload['password']);
            } else {
                unset($payload['password']);
            }

            unset($payload['source_location_id']);
            $user->fill($payload);
            $user->syncAssignedRegisters($registerIds);
            $this->applySourceLocation($user, $sourceLocationId);
            $user->save();
            $user->syncRoles([$payload['role']]);
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                return $this->jsonFromValidation($exception);
            }

            throw $exception;
        }

        if ($request->expectsJson()) {
            return $this->jsonOk($this->serializeUser($user->fresh(['registers'])), __('toasts.user_updated'));
        }

        return redirect()
            ->route('users.index')
            ->with('toast', ['type' => 'success', 'message' => __('toasts.user_updated')]);
    }

    public function destroy(User $user)
    {
        $this->authorizeAdmin('users.manage');

        if ($user->id === auth()->id()) {
            return response()->json(['message' => __('toasts.cannot_disable_self_session')], 422);
        }

        $user->update(['is_active' => false]);

        return $this->jsonOk(null, __('toasts.user_disabled'));
    }

    private function applySourceLocation(User $user, ?string $sourceLocationId): void
    {
        if ($sourceLocationId !== null && $sourceLocationId !== '') {
            $user->source_location_id = $sourceLocationId;

            return;
        }

        if ($user->register_id) {
            $user->syncSourceLocationFromRegister($user->register_id);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, ?string $editingId = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username,'.($editingId ?? 'NULL').',id'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.($editingId ?? 'NULL').',id'],
            'role' => ['required', 'in:ADMIN,MANAGER,CASHIER'],
            'is_active' => ['boolean'],
            'register_ids' => ['nullable', 'array'],
            'register_ids.*' => ['uuid', 'exists:registers,id'],
            'source_location_id' => ['nullable', 'uuid', 'exists:stock_locations,id'],
        ];

        if ($editingId) {
            $rules['password'] = ['nullable', 'string', 'min:6'];
        } else {
            $rules['password'] = ['required', 'string', 'min:6'];
        }

        $dados = $request->validate($rules);

        return [
            'name' => $dados['name'],
            'username' => $dados['username'],
            'email' => $dados['email'],
            'password' => $dados['password'] ?? null,
            'role' => $dados['role'],
            'is_active' => $dados['is_active'] ?? true,
            'source_location_id' => $dados['source_location_id'] ?? null,
            'register_ids' => $dados['register_ids'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => (string) $user->username,
            'email' => (string) $user->email,
            'role' => (string) ($user->role ?: ($user->getRoleNames()->first() ?? 'MANAGER')),
            'is_active' => (bool) $user->is_active,
            'register_ids' => $user->registers->pluck('id')->all(),
            'source_location_id' => $user->source_location_id,
            'registers' => Register::query()->with('sourceLocation')->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'locations' => StockLocation::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'register_id']),
        ];
    }
}
