<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\Register;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $dados = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'register_code' => ['nullable', 'string'],
        ]);

        $user = User::query()
            ->with(['registers.sourceLocation', 'register.sourceLocation'])
            ->where(function ($q) use ($dados) {
                $q->where('username', $dados['username'])
                    ->orWhere('email', $dados['username']);
            })
            ->first();

        if ($user && Hash::check($dados['password'], $user->password) && ! $user->is_active) {
            return response()->json([
                'message' => EnsureUserIsActive::SUSPENDED_MESSAGE,
                'account_suspended' => true,
            ], 403);
        }

        if (! $user || ! Hash::check($dados['password'], $user->password)) {
            return response()->json([
                'message' => 'Credenciais inválidas.',
            ], 401);
        }

        $registers = $user->assignedRegisters();
        $registerCode = trim((string) ($dados['register_code'] ?? ''));

        if ($registers->isEmpty()) {
            return response()->json([
                'message' => 'Utilizador sem caixa atribuído para operar no POS.',
            ], 422);
        }

        $selectedRegister = null;

        if ($registers->count() === 1) {
            $selectedRegister = $registers->first();
        } elseif ($registerCode !== '') {
            $selectedRegister = $this->findRegisterByCode($registers, $registerCode);
            if (! $selectedRegister) {
                return response()->json([
                    'message' => 'O caixa informado não está atribuído a este utilizador.',
                    'registers' => $this->serializeRegisters($registers),
                ], 422);
            }
        } else {
            return response()->json([
                'message' => 'Selecione o caixa para operar.',
                'requires_register_selection' => true,
                'registers' => $this->serializeRegisters($registers),
            ], 422);
        }

        $user->applyActiveRegister($selectedRegister);
        $user->save();

        $token = auth('api')->login($user);
        $ttl = config('jwt.ttl', 60) * 60;

        return response()->json([
            'access_token' => $token,
            'refresh_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'user' => $this->serializeUser($user, $selectedRegister, $registers),
        ]);
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
                'source_location' => $selectedRegister->sourceLocation ? [
                    'id' => $selectedRegister->sourceLocation->id,
                    'code' => $selectedRegister->sourceLocation->code,
                    'name' => $selectedRegister->sourceLocation->name,
                ] : null,
            ],
        ];
    }
}
