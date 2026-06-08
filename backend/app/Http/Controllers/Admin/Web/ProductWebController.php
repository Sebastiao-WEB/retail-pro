<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Web\Concerns\AuthorizesAdminWeb;
use App\Http\Controllers\Admin\Web\Concerns\RespondsAsJson;
use App\Models\Product;
use App\Support\ProductValidation;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductWebController extends Controller
{
    use AuthorizesAdminWeb;
    use RespondsAsJson;

    public function index(Request $request)
    {
        $this->authorizeAdmin('products.view');

        $search = $request->string('search')->toString();

        $produtos = Product::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('nome', 'like', "%{$search}%")
                        ->orWhere('codigo_barras', 'like', "%{$search}%")
                        ->orWhere('categoria', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.products.index', [
            'produtos' => $produtos,
            'search' => $search,
            'canManage' => auth()->user()?->can('products.manage') ?? false,
            'canReload' => auth()->user()?->can('stock.reload') ?? false,
        ]);
    }

    public function edit(Request $request, Product $product)
    {
        $this->authorizeAdmin('products.manage');

        $search = $request->string('search')->toString();

        return view('admin.products.edit', [
            'product' => $product,
            'search' => $search,
            'backUrl' => route('products.index', array_filter([
                'search' => $search !== '' ? $search : null,
            ])),
        ]);
    }

    public function show(Product $product)
    {
        $this->authorizeAdmin('products.manage');

        return $this->jsonOk($this->serializeProduct($product));
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin('products.manage');

        try {
            $payload = $this->validatedPayload($request);
            $product = Product::query()->create([
                ...$payload,
                'stock' => 0,
            ]);
        } catch (ValidationException $exception) {
            return $this->jsonFromValidation($exception);
        }

        return $this->jsonOk(
            $this->serializeProduct($product),
            __('toasts.product_created_reload'),
            201
        );
    }

    public function update(Request $request, Product $product)
    {
        $this->authorizeAdmin('products.manage');

        try {
            $payload = $this->validatedPayload($request, $product->id);
            $product->update($payload);
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                return $this->jsonFromValidation($exception);
            }

            throw $exception;
        }

        if ($request->expectsJson()) {
            return $this->jsonOk($this->serializeProduct($product->fresh()), __('toasts.product_updated'));
        }

        return redirect()
            ->route('products.index', array_filter([
                'search' => $request->string('return_search')->toString() ?: null,
            ]))
            ->with('toast', ['type' => 'success', 'message' => __('toasts.product_updated')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, ?string $editingId = null): array
    {
        $dados = $request->validate(
            [
                'nome' => ['required', 'string', 'max:255'],
                'codigo_barras' => ProductValidation::regrasCodigoBarras($editingId),
                'categoria' => ['nullable', 'string', 'max:255'],
                'unidade_venda' => ['required', 'in:UN,KG'],
                'preco_compra' => ['required', 'numeric'],
                'preco_venda' => ['required', 'numeric'],
                'iva_tipo' => ['required', 'in:ISENTO,PERCENTUAL,MONETARIO'],
                'iva_percentual' => ['required_if:iva_tipo,PERCENTUAL', 'nullable', 'numeric', 'gte:0'],
                'iva_valor' => ['required_if:iva_tipo,MONETARIO', 'nullable', 'numeric', 'gte:0'],
                'is_active' => ['boolean'],
            ],
            [],
            [
                'nome' => __('app.fields.name'),
                'codigo_barras' => __('app.fields.barcode'),
                'categoria' => __('app.fields.category'),
                'unidade_venda' => __('app.fields.sale_unit'),
                'preco_compra' => __('app.fields.purchase_price'),
                'preco_venda' => __('app.fields.sale_price'),
                'iva_percentual' => __('app.fields.iva_percent'),
                'iva_valor' => __('app.fields.iva_amount'),
            ]
        );

        $ivaTipo = $dados['iva_tipo'];
        $ivaPercentual = $ivaTipo === 'PERCENTUAL' ? (float) ($dados['iva_percentual'] ?? 0) : 0.0;
        $ivaValor = $ivaTipo === 'MONETARIO' ? (float) ($dados['iva_valor'] ?? 0) : 0.0;

        if ($ivaTipo === 'PERCENTUAL' && $ivaPercentual <= 0) {
            throw ValidationException::withMessages([
                'iva_percentual' => [__('pages.products.iva_percent_required')],
            ]);
        }

        if ($ivaTipo === 'MONETARIO' && $ivaValor <= 0) {
            throw ValidationException::withMessages([
                'iva_valor' => [__('pages.products.iva_amount_required')],
            ]);
        }

        return [
            'nome' => $dados['nome'],
            'codigo_barras' => ProductValidation::normalizarCodigoBarras($dados['codigo_barras'] ?? null),
            'categoria' => ($dados['categoria'] ?? null) ?: null,
            'unidade_venda' => ProductValidation::normalizarUnidadeVenda($dados['unidade_venda'] ?? null),
            'preco_compra' => $dados['preco_compra'],
            'preco_venda' => $dados['preco_venda'],
            'iva_tipo' => $ivaTipo,
            'iva_valor' => $ivaValor,
            'iva_percentual' => $ivaPercentual,
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'nome' => $product->nome,
            'codigo_barras' => (string) ($product->codigo_barras ?? ''),
            'categoria' => (string) ($product->categoria ?? ''),
            'unidade_venda' => ProductValidation::normalizarUnidadeVenda($product->unidade_venda),
            'preco_compra' => (string) $product->preco_compra,
            'preco_venda' => (string) $product->preco_venda,
            'iva_tipo' => (string) ($product->iva_tipo ?? 'ISENTO'),
            'iva_percentual' => (string) $product->iva_percentual,
            'iva_valor' => (string) $product->iva_valor,
            'stock' => (string) $product->stock,
            'is_active' => (bool) $product->is_active,
        ];
    }
}
