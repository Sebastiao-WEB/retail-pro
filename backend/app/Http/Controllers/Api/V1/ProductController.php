<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $search = request()->string('search')->toString();

        $query = Product::query()->where('is_active', true);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('codigo_barras', 'like', "%{$search}%");
            });
        }

        $produtos = $query->orderBy('nome')->get()->map(function (Product $produto) {
            return [
                'id' => $produto->id,
                'nome' => $produto->nome,
                'codigoBarras' => $produto->codigo_barras,
                'categoria' => $produto->categoria,
                'precoCompra' => (float) $produto->preco_compra,
                'precoVenda' => (float) $produto->preco_venda,
                'ivaTipo' => $produto->iva_tipo,
                'ivaValor' => (float) $produto->iva_valor,
                'ivaPercentual' => (float) $produto->iva_percentual,
                'stock' => (float) $produto->stock,
            ];
        });

        return response()->json(['data' => $produtos]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'codigoBarras' => ['nullable', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:255'],
            'precoCompra' => ['nullable', 'numeric'],
            'precoVenda' => ['required', 'numeric'],
            'ivaTipo' => ['nullable', 'in:ISENTO,PERCENTUAL,MONETARIO'],
            'ivaValor' => ['nullable', 'numeric', 'gte:0'],
            'ivaPercentual' => ['nullable', 'numeric', 'gte:0'],
            'stock' => ['nullable', 'numeric'],
        ]);

        $ivaTipo = $dados['ivaTipo'] ?? 'ISENTO';
        $ivaPercentual = $ivaTipo === 'PERCENTUAL' ? (float) ($dados['ivaPercentual'] ?? 0) : 0.0;
        $ivaValor = $ivaTipo === 'MONETARIO' ? (float) ($dados['ivaValor'] ?? 0) : 0.0;

        if ($ivaTipo === 'PERCENTUAL' && $ivaPercentual <= 0) {
            return response()->json([
                'message' => 'IVA percentual inválido.',
                'errors' => ['ivaPercentual' => ['Informe um percentual de IVA maior que zero.']],
            ], 422);
        }

        if ($ivaTipo === 'MONETARIO' && $ivaValor <= 0) {
            return response()->json([
                'message' => 'IVA monetário inválido.',
                'errors' => ['ivaValor' => ['Informe um valor de IVA maior que zero.']],
            ], 422);
        }

        $produto = Product::create([
            'id' => (string) Str::uuid(),
            'nome' => $dados['nome'],
            'codigo_barras' => $dados['codigoBarras'] ?? null,
            'categoria' => $dados['categoria'] ?? null,
            'preco_compra' => $dados['precoCompra'] ?? 0,
            'preco_venda' => $dados['precoVenda'],
            'iva_tipo' => $ivaTipo,
            'iva_valor' => $ivaValor,
            'iva_percentual' => $ivaPercentual,
            'stock' => $dados['stock'] ?? 0,
        ]);

        return response()->json([
            'message' => 'Produto criado com sucesso.',
            'data' => ['id' => $produto->id],
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        return response()->json([
            'data' => [
                'id' => $product->id,
                'nome' => $product->nome,
                'codigoBarras' => $product->codigo_barras,
                'categoria' => $product->categoria,
                'precoCompra' => (float) $product->preco_compra,
                'precoVenda' => (float) $product->preco_venda,
                'ivaTipo' => $product->iva_tipo,
                'ivaValor' => (float) $product->iva_valor,
                'ivaPercentual' => (float) $product->iva_percentual,
                'stock' => (float) $product->stock,
                'isActive' => (bool) $product->is_active,
            ],
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $dados = $request->validate([
            'nome' => ['sometimes', 'string', 'max:255'],
            'codigoBarras' => ['sometimes', 'nullable', 'string', 'max:255'],
            'categoria' => ['sometimes', 'nullable', 'string', 'max:255'],
            'precoCompra' => ['sometimes', 'numeric'],
            'precoVenda' => ['sometimes', 'numeric'],
            'ivaTipo' => ['sometimes', 'in:ISENTO,PERCENTUAL,MONETARIO'],
            'ivaValor' => ['sometimes', 'numeric', 'gte:0'],
            'ivaPercentual' => ['sometimes', 'numeric', 'gte:0'],
            'stock' => ['sometimes', 'numeric'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $ivaTipoFinal = $dados['ivaTipo'] ?? $product->iva_tipo;
        $ivaPercentualFinal = array_key_exists('ivaPercentual', $dados) ? (float) $dados['ivaPercentual'] : (float) $product->iva_percentual;
        $ivaValorFinal = array_key_exists('ivaValor', $dados) ? (float) $dados['ivaValor'] : (float) $product->iva_valor;

        if ($ivaTipoFinal === 'PERCENTUAL') {
            if ($ivaPercentualFinal <= 0) {
                return response()->json([
                    'message' => 'IVA percentual inválido.',
                    'errors' => ['ivaPercentual' => ['Informe um percentual de IVA maior que zero.']],
                ], 422);
            }
            $dados['ivaPercentual'] = $ivaPercentualFinal;
            $dados['ivaValor'] = 0;
        } elseif ($ivaTipoFinal === 'MONETARIO') {
            if ($ivaValorFinal <= 0) {
                return response()->json([
                    'message' => 'IVA monetário inválido.',
                    'errors' => ['ivaValor' => ['Informe um valor de IVA maior que zero.']],
                ], 422);
            }
            $dados['ivaValor'] = $ivaValorFinal;
            $dados['ivaPercentual'] = 0;
        } else {
            $dados['ivaValor'] = 0;
            $dados['ivaPercentual'] = 0;
        }

        $mapeamento = [
            'nome' => 'nome',
            'codigoBarras' => 'codigo_barras',
            'categoria' => 'categoria',
            'precoCompra' => 'preco_compra',
            'precoVenda' => 'preco_venda',
            'ivaTipo' => 'iva_tipo',
            'ivaValor' => 'iva_valor',
            'ivaPercentual' => 'iva_percentual',
            'stock' => 'stock',
            'is_active' => 'is_active',
        ];

        foreach ($dados as $chave => $valor) {
            $product->{$mapeamento[$chave]} = $valor;
        }

        $product->save();

        return response()->json([
            'message' => 'Produto atualizado com sucesso.',
            'data' => ['id' => $product->id],
        ]);
    }

    public function destroy(Product $product)
    {
        $product->update(['is_active' => false]);
        $product->delete();

        return response()->json([
            'message' => 'Produto removido com sucesso.',
            'data' => ['id' => $product->id],
        ]);
    }
}
