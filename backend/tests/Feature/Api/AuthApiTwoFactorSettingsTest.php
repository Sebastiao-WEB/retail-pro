<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\ApiTestHelpers;
use Tests\TestCase;

class AuthApiTwoFactorSettingsTest extends TestCase
{
    use ApiTestHelpers;
    use RefreshDatabase;

    public function test_status_requer_autenticacao(): void
    {
        $this->getJson('/api/v1/auth/two-factor/status')->assertUnauthorized();
    }

    public function test_operador_pode_activar_e_confirmar_2fa_via_api(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);

        $this->postJson('/api/v1/auth/two-factor/enable', [
            'password' => '123456',
        ], $this->authHeaders($token))
            ->assertOk()
            ->assertJson(['pending' => true]);

        $qr = $this->getJson('/api/v1/auth/two-factor/qr-code', $this->authHeaders($token));
        $qr->assertOk()->assertJsonStructure(['svg', 'url']);

        $secret = Fortify::currentEncrypter()->decrypt($ambiente['user']->fresh()->two_factor_secret);
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->postJson('/api/v1/auth/two-factor/confirm', [
            'code' => $code,
        ], $this->authHeaders($token))
            ->assertOk()
            ->assertJson(['enabled' => true]);

        $this->getJson('/api/v1/auth/two-factor/status', $this->authHeaders($token))
            ->assertOk()
            ->assertJson(['enabled' => true, 'pending' => false]);
    }

    public function test_enable_rejeita_senha_incorrecta(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);

        $this->postJson('/api/v1/auth/two-factor/enable', [
            'password' => 'errada',
        ], $this->authHeaders($token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_operador_pode_desactivar_2fa_via_api(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $secret = app(Google2FA::class)->generateSecretKey();

        $ambiente['user']->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $token = $this->loginApi($ambiente['user'], 'Caixa Teste');

        $this->deleteJson('/api/v1/auth/two-factor', [
            'password' => '123456',
        ], $this->authHeaders($token))
            ->assertOk()
            ->assertJson(['enabled' => false]);
    }

    public function test_recovery_codes_exigem_senha(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $secret = app(Google2FA::class)->generateSecretKey();

        $ambiente['user']->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['code-aaaa-bbbb'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $token = $this->loginApi($ambiente['user'], 'Caixa Teste');

        $this->postJson('/api/v1/auth/two-factor/recovery-codes', [
            'password' => '123456',
        ], $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('recovery_codes.0', 'code-aaaa-bbbb');
    }
}
