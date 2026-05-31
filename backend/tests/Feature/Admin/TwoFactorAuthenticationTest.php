<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PermissionCatalog::sync();
        Role::findOrCreate('ADMIN', 'web')->syncPermissions(
            Permission::query()->where('guard_name', 'web')->get()
        );
    }

    public function test_login_sem_2fa_autentica_normalmente(): void
    {
        $user = User::factory()->create([
            'username' => 'admin',
            'password' => Hash::make('password'),
            'role' => 'ADMIN',
        ]);
        $user->assignRole('ADMIN');

        $response = $this->post(route('login.store'), [
            'username' => 'admin',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_com_2fa_redireciona_para_desafio(): void
    {
        $user = $this->criarUtilizadorComDoisFactores('admin2fa');

        $response = $this->post(route('login.store'), [
            'username' => 'admin2fa',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
    }

    public function test_desafio_2fa_com_codigo_valido_autentica(): void
    {
        $user = $this->criarUtilizadorComDoisFactores('admin2fa');
        $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

        $this->post(route('login.store'), [
            'username' => 'admin2fa',
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login'));

        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $response = $this->post(route('two-factor.login.store'), [
            'code' => $code,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_pagina_seguranca_acessivel_autenticado(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $user->assignRole('ADMIN');

        $this->actingAs($user)
            ->get(route('security.settings'))
            ->assertOk()
            ->assertSee(__('auth.security.two_factor_heading'), false);
    }

    public function test_pagina_desafio_2fa_exige_sessao_de_login(): void
    {
        $this->get(route('two-factor.login'))
            ->assertRedirect(route('login'));
    }

    public function test_pagina_desafio_2fa_visivel_apos_credenciais_validas(): void
    {
        $this->criarUtilizadorComDoisFactores('admin2fa');

        $this->post(route('login.store'), [
            'username' => 'admin2fa',
            'password' => 'password',
        ]);

        $this->get(route('two-factor.login'))
            ->assertOk()
            ->assertSee(__('auth.two_factor.heading'), false);
    }

    private function criarUtilizadorComDoisFactores(string $username): User
    {
        $user = User::factory()->create([
            'username' => $username,
            'password' => Hash::make('password'),
            'role' => 'ADMIN',
        ]);
        $user->assignRole('ADMIN');

        $secret = app(Google2FA::class)->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user->fresh();
    }
}
