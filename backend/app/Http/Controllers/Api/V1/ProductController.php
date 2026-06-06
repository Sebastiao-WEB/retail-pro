<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\ProductValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $dados = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'source_location_id' => ['nullable', 'uuid'],
            'location_id' => ['nullable', 'uuid'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $search = (string) ($dados['search'] ?? '');
        $barcode = (string) ($dados['barcode'] ?? '');
        $locationId = (string) ($dados['source_location_id'] ?? ($dados['location_id'] ?? ''));

        $query = Product::query()->where('is_active', true);

        if ($barcode !== '') {
            $query->where('codigo_barras', $barcode);
        } elseif ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('codigo_barras', 'like', "%{$search}%")
                    ->orWhere('categoria', 'like', "%{$search}%");
            });
        }

        if ($request->filled('page')) {
            $perPage = min(50, max(1, (int) ($dados['per_page'] ?? 10)));
            $paginado = $query->orderBy('nome')->paginate($perPage, ['*'], 'page', max(1, (int) $dados['page']));

            return response()->json([
                'data' => collect($paginado->items())
                    ->map(fn (Product $produto) => $this->serializarProduto($produto))
                    ->values(),
                'meta' => [
                    'current_page' => $paginado->currentPage(),
                    'last_page' => $paginado->lastPage(),
                    'per_page' => $paginado->perPage(),
                    'total' => $paginado->total(),
                ],
            ]);
        }

        $produtos = $query->orderBy('nome')->get();

        return response()->json([
            'data' => $produtos
                ->map(fn (Product $produto) => $this->serializarProduto($produto))
                ->values(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'codigoBarras' => ProductValidation::regrasCodigoBarras(),
            'categoria' => ['nullable', 'string', 'max:255'],
            'precoCompra' => ['nullable', 'numeric'],
            'precoVenda' => ['required', 'numeric'],
            'ivaTipo' => ['nullable', 'in:ISENTO,PERCENTUAL,MONETARIO'],
            'ivaValor' => ['nullable', 'numeric', 'gte:0'],
            'ivaPercentual' => ['nullable', 'numeric', 'gte:0'],
            'stock' => ['nullable', 'numeric'],
            'unidadeVenda' => ProductValidation::regrasUnidadeVenda(),
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
            'codigo_barras' => ProductValidation::normalizarCodigoBarras($dados['codigoBarras'] ?? null),
            'categoria' => $dados['categoria'] ?? null,
            'unidade_venda' => ProductValidation::normalizarUnidadeVenda($dados['unidadeVenda'] ?? null),
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
            'data' => $this->serializarProduto($product),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $dados = $request->validate([
            'nome' => ['sometimes', 'string', 'max:255'],
            'codigoBarras' => ProductValidation::regrasCodigoBarras($product->id, true),
            'categoria' => ['sometimes', 'nullable', 'string', 'max:255'],
            'precoCompra' => ['sometimes', 'numeric'],
            'precoVenda' => ['sometimes', 'numeric'],
            'ivaTipo' => ['sometimes', 'in:ISENTO,PERCENTUAL,MONETARIO'],
            'ivaValor' => ['sometimes', 'numeric', 'gte:0'],
            'ivaPercentual' => ['sometimes', 'numeric', 'gte:0'],
            'stock' => ['sometimes', 'numeric'],
            'is_active' => ['sometimes', 'boolean'],
            'unidadeVenda' => ProductValidation::regrasUnidadeVenda(true),
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

        if (array_key_exists('codigoBarras', $dados)) {
            $dados['codigoBarras'] = ProductValidation::normalizarCodigoBarras($dados['codigoBarras']);
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
            'unidadeVenda' => 'unidade_venda',
        ];

        if (array_key_exists('unidadeVenda', $dados)) {
            $dados['unidadeVenda'] = ProductValidation::normalizarUnidadeVenda($dados['unidadeVenda']);
        }

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

        return response()->json([
            'message' => 'Produto desactivado com sucesso.',
            'data' => ['id' => $product->id],
        ]);
    }

    /** @return array<string, mixed> */
    private function serializarProduto(Product $produto): array
    {
        return [
            'id' => $produto->id,
            'nome' => $produto->nome,
            'codigoBarras' => $produto->codigo_barras,
            'categoria' => $produto->categoria,
            'unidadeVenda' => ProductValidation::normalizarUnidadeVenda($produto->unidade_venda),
            'precoCompra' => (float) $produto->preco_compra,
            'precoVenda' => (float) $produto->preco_venda,
            'ivaTipo' => $produto->iva_tipo,
            'ivaValor' => (float) $produto->iva_valor,
            'ivaPercentual' => (float) $produto->iva_percentual,
            'stock' => (float) $produto->stock,
            'isActive' => (bool) $produto->is_active,
        ];
    }
}
