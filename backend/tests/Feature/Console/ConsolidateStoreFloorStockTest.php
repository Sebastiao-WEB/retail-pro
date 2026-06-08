<?php

namespace Tests\Feature\Console;

use App\Models\Product;
use App\Models\Register;
use App\Models\StockBalance;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\ConsolidateStoreFloorStockService;
use App\Support\StoreFloorLocationResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConsolidateStoreFloorStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_consolida_stock_das_caixas_no_piso_partilhado(): void
    {
        $register1 = Register::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'CX-01',
            'name' => 'Caixa 01',
            'is_active' => true,
        ]);
        $register2 = Register::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'CX-02',
            'name' => 'Caixa 02',
            'is_active' => true,
        ]);

        $cx01 = StockLocation::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'LOC-CX01',
            'register_id' => $register1->id,
            'name' => 'Loja - Caixa 01',
            'type' => 'STORE_FLOOR',
            'is_saleable' => true,
            'is_active' => true,
        ]);
        $cx02 = StockLocation::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'LOC-CX02',
            'register_id' => $register2->id,
            'name' => 'Loja - Caixa 02',
            'type' => 'STORE_FLOOR',
            'is_saleable' => true,
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'id' => (string) Str::uuid(),
            'nome' => 'Arroz 1KG',
            'codigo_barras' => '3333333333333',
            'preco_compra' => 50,
            'preco_venda' => 80,
            'iva_tipo' => 'ISENTO',
            'iva_valor' => 0,
            'iva_percentual' => 0,
            'stock' => 15,
            'is_active' => true,
        ]);

        StockBalance::query()->create([
            'id' => (string) Str::uuid(),
            'location_id' => $cx01->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);
        StockBalance::query()->create([
            'id' => (string) Str::uuid(),
            'location_id' => $cx02->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $result = app(ConsolidateStoreFloorStockService::class)->consolidar();

        $loja = StoreFloorLocationResolver::findSharedStoreFloor();
        $this->assertNotNull($loja);
        $this->assertSame(1, $result['products_merged']);
        $this->assertSame(15.0, $result['units_merged']);

        $this->assertDatabaseHas('stock_balances', [
            'location_id' => $loja->id,
            'product_id' => $product->id,
            'quantity' => 15,
        ]);
        $this->assertDatabaseMissing('stock_balances', [
            'location_id' => $cx01->id,
            'product_id' => $product->id,
        ]);
        $this->assertDatabaseMissing('stock_balances', [
            'location_id' => $cx02->id,
            'product_id' => $product->id,
        ]);

        $cx01->refresh();
        $this->assertNull($cx01->register_id);
        $this->assertFalse($cx01->is_saleable);
    }

    public function test_login_usa_piso_partilhado_quando_loc_loja_existe(): void
    {
        $ambiente = $this->criarAmbienteComCaixasELojaPartilhada();

        $resposta = $this->postJson('/api/v1/auth/login', [
            'username' => $ambiente['user']->username,
            'password' => '123456',
            'register_code' => 'CX-01',
        ]);

        $resposta->assertOk();
        $this->assertSame(
            StoreFloorLocationResolver::CODE,
            $resposta->json('user.source_location.code')
        );
        $this->assertSame(
            StoreFloorLocationResolver::CODE,
            $resposta->json('user.register.source_location.code')
        );
    }

    /** @return array{user: User, loja: StockLocation} */
    private function criarAmbienteComCaixasELojaPartilhada(): array
    {
        $register = Register::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'CX-01',
            'name' => 'Caixa 01',
            'is_active' => true,
        ]);

        StockLocation::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'LOC-CX01',
            'register_id' => $register->id,
            'name' => 'Loja - Caixa 01',
            'type' => 'STORE_FLOOR',
            'is_saleable' => true,
            'is_active' => true,
        ]);

        $loja = StoreFloorLocationResolver::ensureExists();

        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Operador',
            'username' => 'operador_loja',
            'email' => 'operador_loja@test.local',
            'password' => bcrypt('123456'),
            'role' => 'CASHIER',
            'register_id' => $register->id,
            'source_location_id' => $loja->id,
            'is_active' => true,
        ]);

        return compact('user', 'loja');
    }
}
