<?php

namespace Tests\Feature\Api;

use App\Models\Register;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\ApiTestHelpers;
use Tests\TestCase;

class AuthMultiRegisterTest extends TestCase
{
    use ApiTestHelpers;
    use RefreshDatabase;

    public function test_login_com_multiplos_caixas_exige_seleccao(): void
    {
        $ambiente = $this->criarAmbienteApi();

        $register2 = Register::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'CX-02',
            'name' => 'Caixa 02',
            'is_active' => true,
        ]);

        $ambiente['user']->syncAssignedRegisters([$ambiente['register']->id, $register2->id]);
        $ambiente['user']->save();

        $resposta = $this->postJson('/api/v1/auth/login', [
            'username' => $ambiente['user']->username,
            'password' => '123456',
        ]);

        $resposta
            ->assertStatus(422)
            ->assertJsonPath('requires_register_selection', true);

        $this->assertCount(2, $resposta->json('registers'));
    }

    public function test_login_com_codigo_de_caixa_atribui_register_activo(): void
    {
        $ambiente = $this->criarAmbienteApi();

        $register2 = Register::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'CX-02',
            'name' => 'Caixa 02',
            'is_active' => true,
        ]);

        $ambiente['user']->syncAssignedRegisters([$ambiente['register']->id, $register2->id]);
        $ambiente['user']->save();

        $resposta = $this->postJson('/api/v1/auth/login', [
            'username' => $ambiente['user']->username,
            'password' => '123456',
            'register_code' => 'CX-02',
        ]);

        $resposta
            ->assertOk()
            ->assertJsonPath('user.register.code', 'CX-02');

        $user = User::query()->find($ambiente['user']->id);
        $this->assertEquals($register2->id, $user->register_id);
        $this->assertEquals('Caixa 02', $user->caixa_atribuido);
    }
}
