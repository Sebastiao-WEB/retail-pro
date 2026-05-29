<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ApiTestHelpers;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use ApiTestHelpers;
    use RefreshDatabase;

    public function test_login_retorna_token_e_dados_do_operador(): void
    {
        $ambiente = $this->criarAmbienteApi();

        $resposta = $this->postJson('/api/v1/auth/login', [
            'username' => $ambiente['user']->username,
            'password' => '123456',
            'register_code' => 'Caixa Teste',
        ]);

        $resposta
            ->assertOk()
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'user' => ['id', 'name', 'register' => ['id', 'source_location' => ['id']]],
            ]);
    }

    public function test_login_rejeita_credenciais_invalidas(): void
    {
        $this->criarAmbienteApi();

        $resposta = $this->postJson('/api/v1/auth/login', [
            'username' => 'operador_teste',
            'password' => 'errada',
        ]);

        $resposta->assertUnauthorized()->assertJson(['message' => 'Credenciais inválidas.']);
    }

    public function test_logout_encerra_sessao_autenticada(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);

        $resposta = $this->postJson('/api/v1/auth/logout', [], $this->authHeaders($token));

        $resposta->assertOk()->assertJson(['message' => 'Sessão encerrada com sucesso.']);
    }
}
