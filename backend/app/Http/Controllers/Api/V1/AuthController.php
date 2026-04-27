<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

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
            ->with(['register.sourceLocation'])
            ->where('username', $dados['username'])
            ->orWhere('email', $dados['username'])
            ->first();

        if (! $user || ! Hash::check($dados['password'], $user->password) || ! $user->is_active) {
            return response()->json([
                'message' => 'Credenciais inválidas.',
            ], 401);
        }

        $registerInformado = trim((string) ($dados['register_code'] ?? ''));
        if ($registerInformado !== '') {
            $registerUser = $user->register;
            $candidatos = array_filter([
                $registerUser?->code,
                $registerUser?->name,
                $user->caixa_atribuido,
            ], fn ($valor) => is_string($valor) && trim($valor) !== '');

            $normalizadoInformado = mb_strtolower($registerInformado);
            $corresponde = collect($candidatos)->contains(
                fn ($valor) => mb_strtolower(trim((string) $valor)) === $normalizadoInformado
            );

            if (! $corresponde) {
                return response()->json([
                    'message' => 'O caixa informado não corresponde ao caixa atribuído ao utilizador.',
                ], 422);
            }
        }

        $token = auth('api')->login($user);
        $ttl = config('jwt.ttl', 60) * 60;

        return response()->json([
            'access_token' => $token,
            'refresh_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'caixa_atribuido' => $user->caixa_atribuido,
                'register' => $user->register ? [
                    'id' => $user->register->id,
                    'code' => $user->register->code,
                    'name' => $user->register->name,
                    'source_location' => $user->register->sourceLocation ? [
                        'id' => $user->register->sourceLocation->id,
                        'code' => $user->register->sourceLocation->code,
                        'name' => $user->register->sourceLocation->name,
                    ] : null,
                ] : null,
            ],
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
            JWTAuth::invalidate(JWTAuth::getToken());
        }

        return response()->json([
            'message' => 'Sessão encerrada com sucesso.',
        ]);
    }
}
