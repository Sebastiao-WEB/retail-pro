<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\ApiTwoFactorChallengeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\ApiTestHelpers;
use Tests\TestCase;

class AuthApiTwoFactorTest extends TestCase
{
    use ApiTestHelpers;
    use RefreshDatabase;

    public function test_login_com_2fa_exige_verificacao(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $this->activarDoisFactores($ambiente['user']);

        $resposta = $this->postJson('/api/v1/auth/login', [
            'username' => $ambiente['user']->username,
            'password' => '123456',
            'register_code' => 'Caixa Teste',
        ]);

        $resposta
            ->assertStatus(422)
            ->assertJson([
                'requires_two_factor' => true,
            ])
            ->assertJsonStructure(['two_factor_token', 'expires_in']);
    }

    public function test_desafio_2fa_com_codigo_valido_emite_jwt(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $secret = $this->activarDoisFactores($ambiente['user']);

        $login = $this->postJson('/api/v1/auth/login', [
            'username' => $ambiente['user']->username,
            'password' => '123456',
            'register_code' => 'Caixa Teste',
        ])->assertStatus(422);

        $token = (string) $login->json('two_factor_token');
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $resposta = $this->postJson('/api/v1/auth/two-factor-challenge', [
            'two_factor_token' => $token,
            'code' => $code,
        ]);

        $resposta
            ->assertOk()
            ->assertJsonStructure([
                'access_token',
                'user' => ['id', 'register' => ['id']],
            ]);
    }

    public function test_desafio_2fa_rejeita_codigo_invalido(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $this->activarDoisFactores($ambiente['user']);

        $login = $this->postJson('/api/v1/auth/login', [
            'username' => $ambiente['user']->username,
            'password' => '123456',
            'register_code' => 'Caixa Teste',
        ])->assertStatus(422);

        $resposta = $this->postJson('/api/v1/auth/two-factor-challenge', [
            'two_factor_token' => $login->json('two_factor_token'),
            'code' => '000000',
        ]);

        $resposta
            ->assertStatus(422)
            ->assertJson([
                'invalid_two_factor_code' => true,
            ]);
    }

    public function test_desafio_2fa_rejeita_token_expirado(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $this->activarDoisFactores($ambiente['user']);

        $login = $this->postJson('/api/v1/auth/login', [
            'username' => $ambiente['user']->username,
            'password' => '123456',
            'register_code' => 'Caixa Teste',
        ])->assertStatus(422);

        $token = (string) $login->json('two_factor_token');
        app(ApiTwoFactorChallengeService::class)->forget($token);

        $resposta = $this->postJson('/api/v1/auth/two-factor-challenge', [
            'two_factor_token' => $token,
            'code' => '123456',
        ]);

        $resposta
            ->assertUnauthorized()
            ->assertJson([
                'two_factor_expired' => true,
            ]);
    }

    private function activarDoisFactores(User $user): string
    {
        $secret = app(Google2FA::class)->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $secret;
    }
}
