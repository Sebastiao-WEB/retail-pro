<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\SupportedLocales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disableCookieEncryption();

        PermissionCatalog::sync();
        Role::findOrCreate('ADMIN', 'web')->syncPermissions(
            Permission::query()->where('guard_name', 'web')->get()
        );
    }

    public function test_pagina_login_respeita_cookie_de_idioma(): void
    {
        $response = $this->withUnencryptedCookie(SupportedLocales::COOKIE_NAME, 'so_SO')
            ->get(route('login'));

        $response->assertOk();
        $response->assertSee('Isticmaale', false);
    }

    public function test_dashboard_em_portugues_por_defeito(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $admin->assignRole('ADMIN');

        $this->actingAs($admin)
            ->withUnencryptedCookie(SupportedLocales::COOKIE_NAME, 'pt_MZ')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Produtos ativos', false);
    }

    public function test_dashboard_em_somali_com_cookie(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $admin->assignRole('ADMIN');

        $this->actingAs($admin)
            ->withUnencryptedCookie(SupportedLocales::COOKIE_NAME, 'so_SO')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Alaab firfircoon', false);
    }
}
