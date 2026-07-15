<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Web\Concerns\AuthorizesAdminWeb;
use App\Http\Controllers\Admin\Web\Concerns\RespondsAsJson;
use App\Models\Product;
use App\Support\ProductStockDisplay;
use App\Models\Purchase;
use App\Models\StockBalance;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Services\StockAdjustmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockReloadWebController extends Controller
{
    use AuthorizesAdminWeb;
    use RespondsAsJson;

    public function history(Request $request)
    {
        $this->authorizeAdmin('stock.reload');

        $search = $request->string('search')->toString();

        $reloadHistory = StockMovement::query()
            ->stockReloads()
            ->with(['product', 'toLocation', 'performedBy', 'reloadRecord'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->whereHas('product', function ($productQuery) use ($search) {
                        $productQuery->where('nome', 'like', "%{$search}%")
                            ->orWhere('codigo_barras', 'like', "%{$search}%");
                    })->orWhere('note', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.stock-reload.history', [
            'reloadHistory' => $reloadHistory,
            'search' => $search,
        ]);
    }

    public function reloadForm(Request $request, Product $product)
    {
        $this->authorizeAdmin('stock.reload');
        abort_unless($product->is_active, 404);

        $search = $request->string('search')->toString();
        $returnTo = $request->string('return_to')->toString();

        return view('admin.stock-reload.reload', [
            'product' => $product,
            'search' => $search,
            'returnTo' => $returnTo,
            'backUrl' => $this->backUrlFromRequest($request),
            'locations' => $this->activeLocations(),
            'defaultLocationId' => $this->defaultLocationId(),
        ]);
    }

    public function adjustForm(Request $request, Product $product)
    {
        $this->authorizeAdmin('stock.reload');
        abort_unless($product->is_active, 404);

        $search = $request->string('search')->toString();
        $returnTo = $request->string('return_to')->toString();
        $defaultLocationId = $this->defaultLocationId();

        return view('admin.stock-reload.adjust', [
            'product' => $product,
            'search' => $search,
            'returnTo' => $returnTo,
            'backUrl' => $this->backUrlFromRequest($request),
            'locations' => $this->activeLocations(),
            'defaultLocationId' => $defaultLocationId,
            'initialBalance' => $this->balanceAtLocation($product->id, $defaultLocationId),
            'balanceUrl' => route('stock.reload.balance'),
        ]);
    }

    public function balance(Request $request)
    {
        $this->authorizeAdmin('stock.reload');

        $request->validate([
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'to_location_id' => ['required', 'uuid', 'exists:stock_locations,id'],
        ]);

        $saldo = $this->balanceAtLocation(
            $request->string('product_id')->toString(),
            $request->string('to_location_id')->toString(),
        );

        return $this->jsonOk(['saldo' => $saldo]);
    }

    public function adjust(Request $request, StockAdjustmentService $adjustmentService)
    {
        $this->authorizeAdmin('stock.reload');

        try {
            $dados = $request->validate([
                'productId' => ['required', 'uuid', 'exists:products,id'],
                'to_location_id' => ['required', 'uuid', 'exists:stock_locations,id'],
                'adjustmentDelta' => ['required', 'numeric', 'not_in:0'],
                'note' => ['nullable', 'string', 'max:500'],
                'unitCost' => ['nullable', 'numeric', 'gte:0'],
            ]);

            $adjustmentService->aplicar(
                $dados['productId'],
                $dados['to_location_id'],
                (float) $dados['adjustmentDelta'],
                $dados['note'] ?? null,
                auth()->id(),
                (float) ($dados['unitCost'] ?? 0),
            );
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                return $this->jsonFromValidation($exception);
            }

            throw $exception;
        }

        if ($request->expectsJson()) {
            return $this->jsonOk(null, __('toasts.stock_adjusted'));
        }

        return redirect()
            ->to($this->redirectUrlFromRequest($request))
            ->with('toast', ['type' => 'success', 'message' => __('toasts.stock_adjusted')]);
    }

    public function reload(Request $request)
    {
        $this->authorizeAdmin('stock.reload');

        try {
            $dados = $request->validate([
                'productId' => ['required', 'uuid', 'exists:products,id'],
                'quantity' => ['required', 'numeric', 'gt:0'],
                'unitCost' => ['required', 'numeric', 'gte:0'],
                'supplier' => ['required', 'string', 'max:255'],
                'note' => ['nullable', 'string', 'max:500'],
                'to_location_id' => ['required', 'uuid', 'exists:stock_locations,id'],
            ]);
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                return $this->jsonFromValidation($exception);
            }

            throw $exception;
        }

        ProductStockDisplay::exigirLocalizacaoActiva($dados['to_location_id'], 'to_location_id');

        DB::transaction(function () use ($dados) {
            $product = Product::query()->findOrFail($dados['productId']);
            $quantidade = (float) $dados['quantity'];
            $custoUnitario = (float) $dados['unitCost'];
            $total = $quantidade * $custoUnitario;

            $purchase = Purchase::query()->create([
                'id' => (string) Str::uuid(),
                'fornecedor' => $dados['supplier'],
                'total' => $total,
                'data' => now(),
                'itens' => [[
                    'product_id' => $product->id,
                    'nome' => $product->nome,
                    'quantidade' => $quantidade,
                    'custo_unitario' => $custoUnitario,
                    'total' => $total,
                    'note' => $dados['note'] ?? null,
                    'tipo' => 'STOCK_RELOAD',
                ]],
            ]);

            $balance = StockBalance::query()->firstOrCreate(
                [
                    'location_id' => $dados['to_location_id'],
                    'product_id' => $product->id,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'quantity' => 0,
                ]
            );
            $balance->quantity = (float) $balance->quantity + $quantidade;
            $balance->save();

            StockMovement::query()->create([
                'id' => (string) Str::uuid(),
                'product_id' => $product->id,
                'from_location_id' => null,
                'to_location_id' => $dados['to_location_id'],
                'type' => 'IN',
                'quantity' => $quantidade,
                'unit_cost' => $custoUnitario,
                'reference_type' => 'STOCK_RELOAD',
                'reference_id' => $purchase->id,
                'note' => $dados['note'] ?: 'Recarregamento manual de stock',
                'performed_by' => auth()->id(),
            ]);

            ProductStockDisplay::sincronizarStockGlobal($product->id);
            $product->preco_compra = $custoUnitario;
            $product->save();
        });

        if ($request->expectsJson()) {
            return $this->jsonOk(null, __('toasts.stock_reloaded'));
        }

        return redirect()
            ->to($this->redirectUrlFromRequest($request))
            ->with('toast', ['type' => 'success', 'message' => __('toasts.stock_reloaded')]);
    }

    /** @return \Illuminate\Support\Collection<int, StockLocation> */
    private function activeLocations()
    {
        return StockLocation::query()
            ->where('is_active', true)
            ->orderBy('is_saleable', 'desc')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type']);
    }

    private function defaultLocationId(): ?string
    {
        return StockLocation::query()
            ->where('is_active', true)
            ->orderBy('is_saleable', 'desc')
            ->orderBy('name')
            ->value('id');
    }

    private function balanceAtLocation(string $productId, ?string $locationId): float
    {
        if ($locationId === null || $locationId === '' || ! ProductStockDisplay::localizacaoEstaActiva($locationId)) {
            return 0.0;
        }

        return (float) (StockBalance::query()
            ->where('location_id', $locationId)
            ->where('product_id', $productId)
            ->value('quantity') ?? 0);
    }

    private function backUrlFromRequest(Request $request): string
    {
        return $this->redirectUrlFromRequest($request);
    }

    private function redirectUrlFromRequest(Request $request): string
    {
        $returnTo = $request->string('return_to')->toString();
        $search = $request->string('return_search')->toString() ?: $request->string('search')->toString();

        return route('products.index', array_filter([
            'search' => $search !== '' ? $search : null,
        ]));
    }
}
