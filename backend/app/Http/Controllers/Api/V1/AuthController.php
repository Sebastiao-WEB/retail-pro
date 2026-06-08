<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\Register;
use App\Support\StoreFloorLocationResolver;
use App\Models\User;
use App\Services\ApiTwoFactorChallengeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $dados = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'register_code' => ['nullable', 'string'],
        ]);

        $user = $this->findUserByCredentials($dados['username'], $dados['password']);
        if ($user instanceof \Illuminate\Http\JsonResponse) {
            return $user;
        }

        $registers = $user->assignedRegisters();
        $registerCode = trim((string) ($dados['register_code'] ?? ''));

        if ($registers->isEmpty()) {
            return response()->json([
                'message' => 'Utilizador sem caixa atribuído para operar no POS.',
            ], 422);
        }

        $selectedRegister = $this->resolveRegister($registers, $registerCode);
        if ($selectedRegister instanceof \Illuminate\Http\JsonResponse) {
            return $selectedRegister;
        }

        if ($this->userRequiresTwoFactor($user)) {
            $token = app(ApiTwoFactorChallengeService::class)->create(
                $user->id,
                $selectedRegister->id
            );

            return response()->json([
                'message' => 'Confirme o acesso com autenticação em dois factores.',
                'requires_two_factor' => true,
                'two_factor_token' => $token,
                'expires_in' => ApiTwoFactorChallengeService::TTL_SECONDS,
            ], 422);
        }

        return $this->issueTokenResponse($user, $selectedRegister, $registers);
    }

    public function adminLogin(Request $request)
    {
        $dados = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = $this->findUserByCredentials($dados['username'], $dados['password']);
        if ($user instanceof \Illuminate\Http\JsonResponse) {
            return $user;
        }

        if (! $this->userCanAccessMobileAdmin($user)) {
            return response()->json([
                'message' => 'Acesso mobile reservado a gerentes e administradores.',
            ], 403);
        }

        if ($this->userRequiresTwoFactor($user)) {
            $token = app(ApiTwoFactorChallengeService::class)->create($user->id, null);

            return response()->json([
                'message' => 'Confirme o acesso com autenticação em dois factores.',
                'requires_two_factor' => true,
                'two_factor_token' => $token,
                'expires_in' => ApiTwoFactorChallengeService::TTL_SECONDS,
                'client' => 'admin',
            ], 422);
        }

        return $this->issueAdminTokenResponse($user);
    }

    public function me(Request $request)
    {
        $user = $request->user('api');
        abort_unless($user, 401);

        $user->loadMissing(['registers.sourceLocation', 'register.sourceLocation', 'sourceLocation']);

        $registers = $user->assignedRegisters();
        $selectedRegister = $user->register_id
            ? $registers->firstWhere('id', $user->register_id) ?? Register::query()->find($user->register_id)
            : null;

        if ($selectedRegister instanceof Register) {
            return response()->json([
                'user' => $this->serializeUser($user, $selectedRegister, $registers),
                'client' => 'pos',
            ]);
        }

        return response()->json([
            'user' => $this->serializeAdminUser($user),
            'client' => 'admin',
        ]);
    }

    public function twoFactorChallenge(Request $request)
    {
        $dados = $request->validate([
            'two_factor_token' => ['required', 'string', 'uuid'],
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        if (blank($dados['code'] ?? null) && blank($dados['recovery_code'] ?? null)) {
            return response()->json([
                'message' => 'Informe o código de autenticação ou um código de recuperação.',
            ], 422);
        }

        $challenge = app(ApiTwoFactorChallengeService::class)->get($dados['two_factor_token']);
        if (! $challenge) {
            return response()->json([
                'message' => 'Sessão de verificação expirada. Faça login novamente.',
                'two_factor_expired' => true,
            ], 401);
        }

        $user = User::query()
            ->with(['registers.sourceLocation', 'register.sourceLocation', 'sourceLocation'])
            ->find($challenge['user_id']);

        if (! $user || ! $user->is_active) {
            app(ApiTwoFactorChallengeService::class)->forget($dados['two_factor_token']);

            return response()->json([
                'message' => $user && ! $user->is_active
                    ? EnsureUserIsActive::SUSPENDED_MESSAGE
                    : 'Utilizador não encontrado.',
                'account_suspended' => $user && ! $user->is_active,
            ], $user && ! $user->is_active ? 403 : 401);
        }

        if (! $this->userRequiresTwoFactor($user)) {
            app(ApiTwoFactorChallengeService::class)->forget($dados['two_factor_token']);

            return response()->json([
                'message' => 'Autenticação em dois factores não está activa para este utilizador.',
            ], 422);
        }

        if (! $this->verifyTwoFactorCode($user, $dados)) {
            return response()->json([
                'message' => 'Código de autenticação inválido.',
                'invalid_two_factor_code' => true,
            ], 422);
        }

        $selectedRegister = filled($challenge['register_id'] ?? null)
            ? Register::query()->find($challenge['register_id'])
            : null;

        if (filled($challenge['register_id'] ?? null) && ! $selectedRegister) {
            app(ApiTwoFactorChallengeService::class)->forget($dados['two_factor_token']);

            return response()->json([
                'message' => 'Caixa associado à sessão de verificação não encontrado.',
            ], 422);
        }

        app(ApiTwoFactorChallengeService::class)->forget($dados['two_factor_token']);

        if ($selectedRegister instanceof Register) {
            return $this->issueTokenResponse($user, $selectedRegister, $user->assignedRegisters());
        }

        if (! $this->userCanAccessMobileAdmin($user)) {
            return response()->json([
                'message' => 'Acesso mobile reservado a gerentes e administradores.',
            ], 403);
        }

        return $this->issueAdminTokenResponse($user);
    }

    public function refresh()
    {
        try {
            $token = auth('api')->refresh();
        } catch (\Throwable) {
            return response()->json([
                'message' => 'Não foi possível renovar sessão.',
            ], 401);
        }

        $ttl = config('jwt.ttl', 60) * 60;

        return response()->json([
            'access_token' => $token,
            'refresh_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
        ]);
    }

    public function logout()
    {
        try {
            auth('api')->logout();
        } catch (\Throwable) {
            \PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::invalidate(\PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::getToken());
        }

        return response()->json([
            'message' => 'Sessão encerrada com sucesso.',
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user('api');
        abort_unless($user, 401);

        $dados = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::default()],
        ], [
            'current_password.required' => 'Informe a senha actual.',
            'password.required' => 'Informe a nova senha.',
            'password.confirmed' => 'A confirmação da nova senha não coincide.',
        ]);

        if (! Hash::check($dados['current_password'], $user->password)) {
            return response()->json([
                'message' => 'A senha actual está incorrecta.',
                'errors' => [
                    'current_password' => ['A senha actual está incorrecta.'],
                ],
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make($dados['password']),
        ])->save();

        return response()->json([
            'message' => 'Senha actualizada com sucesso.',
        ]);
    }

    private function findUserByCredentials(string $username, string $password): User|\Illuminate\Http\JsonResponse
    {
        $user = User::query()
            ->with(['registers.sourceLocation', 'register.sourceLocation', 'sourceLocation'])
            ->where(function ($q) use ($username) {
                $q->where('username', $username)
                    ->orWhere('email', $username);
            })
            ->first();

        if ($user && Hash::check($password, $user->password) && ! $user->is_active) {
            return response()->json([
                'message' => EnsureUserIsActive::SUSPENDED_MESSAGE,
                'account_suspended' => true,
            ], 403);
        }

        if (! $user || ! Hash::check($password, $user->password)) {
            return response()->json([
                'message' => 'Credenciais inválidas.',
            ], 401);
        }

        return $user;
    }

    private function resolveRegister($registers, string $registerCode): Register|\Illuminate\Http\JsonResponse
    {
        if ($registers->count() === 1) {
            return $registers->first();
        }

        if ($registerCode !== '') {
            $selectedRegister = $this->findRegisterByCode($registers, $registerCode);
            if (! $selectedRegister) {
                return response()->json([
                    'message' => 'O caixa informado não está atribuído a este utilizador.',
                    'registers' => $this->serializeRegisters($registers),
                ], 422);
            }

            return $selectedRegister;
        }

        return response()->json([
            'message' => 'Selecione o caixa para operar.',
            'requires_register_selection' => true,
            'registers' => $this->serializeRegisters($registers),
        ], 422);
    }

    private function userRequiresTwoFactor(User $user): bool
    {
        return ! is_null($user->two_factor_secret)
            && ! is_null($user->two_factor_confirmed_at);
    }

    /** @param  array<string, mixed>  $dados */
    private function verifyTwoFactorCode(User $user, array $dados): bool
    {
        if (filled($dados['recovery_code'] ?? null)) {
            $recoveryCode = (string) $dados['recovery_code'];
            $valid = collect($user->recoveryCodes())->contains(
                fn (string $code) => hash_equals($code, $recoveryCode)
            );

            if ($valid) {
                $user->replaceRecoveryCode($recoveryCode);
            }

            return $valid;
        }

        $provider = app(TwoFactorAuthenticationProvider::class);

        return $provider->verify(
            Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
            (string) ($dados['code'] ?? '')
        );
    }

    private function issueAdminTokenResponse(User $user)
    {
        $token = auth('api')->login($user);
        $ttl = config('jwt.ttl', 60) * 60;

        return response()->json([
            'access_token' => $token,
            'refresh_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'client' => 'admin',
            'user' => $this->serializeAdminUser($user),
        ]);
    }

    private function userCanAccessMobileAdmin(User $user): bool
    {
        if (in_array((string) ($user->role ?? ''), ['ADMIN', 'MANAGER'], true)) {
            return true;
        }

        if ($user->hasRole(['ADMIN', 'MANAGER'])) {
            return true;
        }

        return $user->can('dashboard.view');
    }

    private function serializeAdminUser(User $user): array
    {
        $registers = $user->assignedRegisters();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first() ?? $user->role,
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'caixa_atribuido' => $user->caixa_atribuido,
            'registers' => $this->serializeRegisters($registers),
        ];
    }

    private function issueTokenResponse(User $user, Register $selectedRegister, $allRegisters)
    {
        $user->applyActiveRegister($selectedRegister);
        $user->save();

        $token = auth('api')->login($user);
        $ttl = config('jwt.ttl', 60) * 60;

        return response()->json([
            'access_token' => $token,
            'refresh_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'user' => $this->serializeUser($user, $selectedRegister, $allRegisters),
        ]);
    }

    private function findRegisterByCode($registers, string $code): ?Register
    {
        $normalizado = mb_strtolower(trim($code));

        return $registers->first(function (Register $register) use ($normalizado) {
            $candidatos = array_filter([
                $register->code,
                $register->name,
            ], fn ($valor) => is_string($valor) && trim($valor) !== '');

            return collect($candidatos)->contains(
                fn ($valor) => mb_strtolower(trim((string) $valor)) === $normalizado
            );
        });
    }

    private function serializeRegisters($registers): array
    {
        return $registers->map(fn (Register $register) => [
            'id' => $register->id,
            'code' => $register->code,
            'name' => $register->name,
        ])->values()->all();
    }

    private function serializeUser(User $user, Register $selectedRegister, $allRegisters): array
    {
        $selectedRegister->loadMissing('sourceLocation');
        $user->loadMissing('sourceLocation');

        $sourceLocation = $this->resolveSourceLocationPayload($user, $selectedRegister);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role,
            'caixa_atribuido' => $user->caixa_atribuido,
            'registers' => $this->serializeRegisters($allRegisters),
            'register' => [
                'id' => $selectedRegister->id,
                'code' => $selectedRegister->code,
                'name' => $selectedRegister->name,
                'source_location' => $sourceLocation,
            ],
            'source_location' => $sourceLocation,
        ];
    }

    /**
     * Local de stock para o POS: piso de loja partilhado (supermercado) ou fallback legado por caixa.
     *
     * @return array{id: string, code: string, name: string}|null
     */
    private function resolveSourceLocationPayload(User $user, Register $register): ?array
    {
        return StoreFloorLocationResolver::payloadForPos($user, $register);
    }
}
