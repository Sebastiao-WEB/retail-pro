<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Web\Concerns\AuthorizesAdminWeb;
use App\Http\Controllers\Admin\Web\Concerns\RespondsAsJson;
use App\Models\Product;
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

    public function index(Request $request)
    {
        $this->authorizeAdmin('stock.reload');

        $search = $request->string('search')->toString();

        $products = Product::query()
            ->where('is_active', true)
            ->when($search !== '', function ($q) use ($search) {
                $q->where(fn ($inner) => $inner
                    ->where('nome', 'like', "%{$search}%")
                    ->orWhere('codigo_barras', 'like', "%{$search}%")
                    ->orWhere('categoria', 'like', "%{$search}%"));
            })
            ->orderBy('nome')
            ->paginate(12)
            ->withQueryString();

        $reloadHistory = StockMovement::query()
            ->stockReloads()
            ->with(['product', 'toLocation', 'performedBy', 'reloadRecord'])
            ->latest()
            ->paginate(10, ['*'], 'historyPage');

        return view('admin.stock-reload.index', [
            'products' => $products,
            'reloadHistory' => $reloadHistory,
            'search' => $search,
            'locations' => StockLocation::query()
                ->where('is_active', true)
                ->orderBy('is_saleable', 'desc')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'type']),
            'defaultLocationId' => StockLocation::query()
                ->where('is_active', true)
                ->orderBy('is_saleable', 'desc')
                ->orderBy('name')
                ->value('id'),
        ]);
    }

    public function balance(Request $request)
    {
        $this->authorizeAdmin('stock.reload');

        $request->validate([
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'to_location_id' => ['required', 'uuid', 'exists:stock_locations,id'],
        ]);

        $saldo = (float) (StockBalance::query()
            ->where('location_id', $request->string('to_location_id'))
            ->where('product_id', $request->string('product_id'))
            ->value('quantity') ?? 0);

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
            return $this->jsonFromValidation($exception);
        }

        return $this->jsonOk(null, __('toasts.stock_adjusted'));
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
            return $this->jsonFromValidation($exception);
        }

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

            $product->stock = (float) StockBalance::query()
                ->where('product_id', $product->id)
                ->sum('quantity');
            $product->preco_compra = $custoUnitario;
            $product->save();
        });

        return $this->jsonOk(null, __('toasts.stock_reloaded'));
    }
}
