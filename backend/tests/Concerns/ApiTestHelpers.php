<?php

namespace Tests\Concerns;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Register;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Support\Str;

trait ApiTestHelpers
{
    protected function criarAmbienteApi(): array
    {
        $register = Register::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'CX-TEST',
            'name' => 'Caixa Teste',
            'is_active' => true,
        ]);

        $location = StockLocation::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'LOC-TEST',
            'register_id' => $register->id,
            'name' => 'Loja Teste',
            'type' => 'STORE_FLOOR',
            'is_saleable' => true,
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Operador Teste',
            'username' => 'operador_teste',
            'email' => 'operador_teste@retailpro.local',
            'password' => bcrypt('123456'),
            'role' => 'CASHIER',
            'caixa_atribuido' => 'Caixa Teste',
            'register_id' => $register->id,
            'source_location_id' => $location->id,
            'is_active' => true,
        ]);

        Customer::query()->create([
            'id' => (string) Str::uuid(),
            'nome' => 'Cliente Geral',
            'telefone' => '840000000',
            'is_active' => true,
        ]);

        Product::query()->create([
            'id' => (string) Str::uuid(),
            'nome' => 'Produto Teste',
            'codigo_barras' => '9999999999999',
            'preco_compra' => 50,
            'preco_venda' => 100,
            'iva_tipo' => 'ISENTO',
            'iva_valor' => 0,
            'iva_percentual' => 0,
            'stock' => 100,
            'is_active' => true,
        ]);

        return compact('register', 'location', 'user');
    }

    protected function loginApi(User $user, ?string $registerCode = null): string
    {
        $payload = [
            'username' => $user->username,
            'password' => '123456',
        ];

        if ($registerCode !== null) {
            $payload['register_code'] = $registerCode;
        }

        $resposta = $this->postJson('/api/v1/auth/login', $payload);
        $resposta->assertOk();

        return (string) $resposta->json('access_token');
    }

    protected function authHeaders(string $token): array
    {
        return [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ];
    }
}
