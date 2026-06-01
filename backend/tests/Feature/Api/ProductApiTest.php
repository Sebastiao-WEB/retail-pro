<?php

namespace Tests\Feature\Api;

use App\Models\StockBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ApiTestHelpers;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use ApiTestHelpers;
    use RefreshDatabase;

    public function test_lista_stock_por_localizacao(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);
        $produto = $ambiente['product'];

        StockBalance::query()
            ->where('location_id', $ambiente['location']->id)
            ->where('product_id', $produto->id)
            ->update(['quantity' => 42]);

        $respostaGlobal = $this->getJson('/api/v1/products', $this->authHeaders($token));
        $respostaGlobal->assertOk();
        $this->assertSame(100.0, (float) collect($respostaGlobal->json('data'))->firstWhere('id', $produto->id)['stock']);

        $respostaLocal = $this->getJson(
            '/api/v1/products?source_location_id='.$ambiente['location']->id,
            $this->authHeaders($token)
        );
        $respostaLocal->assertOk();
        $this->assertSame(42.0, (float) collect($respostaLocal->json('data'))->firstWhere('id', $produto->id)['stock']);
        $this->assertSame('UN', collect($respostaLocal->json('data'))->firstWhere('id', $produto->id)['unidadeVenda']);
    }

    public function test_lista_unidade_venda_kg(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);
        $ambiente['product']->update(['unidade_venda' => 'KG']);

        $resposta = $this->getJson('/api/v1/products', $this->authHeaders($token));

        $resposta->assertOk();
        $this->assertSame('KG', $resposta->json('data.0.unidadeVenda'));
    }

    public function test_filtra_produtos_por_pesquisa(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);

        $resposta = $this->getJson('/api/v1/products?search=Produto%20Teste', $this->authHeaders($token));

        $resposta->assertOk();
        $this->assertCount(1, $resposta->json('data'));
        $this->assertSame('Produto Teste', $resposta->json('data.0.nome'));
    }

    public function test_filtra_produto_por_codigo_barras_exacto(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);
        $produto = $ambiente['product'];
        $produto->update(['codigo_barras' => '7891234567890']);

        $resposta = $this->getJson(
            '/api/v1/products?barcode=7891234567890&source_location_id='.$ambiente['location']->id,
            $this->authHeaders($token)
        );

        $resposta->assertOk();
        $this->assertCount(1, $resposta->json('data'));
        $this->assertSame($produto->id, $resposta->json('data.0.id'));
    }

    public function test_rejeita_criar_produto_com_codigo_barras_duplicado(): void
    {
        $ambiente = $this->criarAmbienteApi();
        $token = $this->loginApi($ambiente['user']);
        $ambiente['product']->update(['codigo_barras' => '5555555555555']);

        $resposta = $this->postJson('/api/v1/products', [
            'nome' => 'Outro produto',
            'codigoBarras' => '5555555555555',
            'precoVenda' => 10,
        ], $this->authHeaders($token));

        $resposta->assertStatus(422)->assertJsonValidationErrors(['codigoBarras']);
        $this->assertDatabaseCount('products', 1);
    }
}
