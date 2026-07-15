<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Support\ProductStockDisplay;
use App\Services\StockAdjustmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function reload(Request $request)
    {
        $dados = $request->validate([
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['required', 'numeric', 'gte:0'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
            'to_location_id' => ['required', 'uuid', 'exists:stock_locations,id'],
        ]);

        ProductStockDisplay::exigirLocalizacaoActiva($dados['to_location_id'], 'to_location_id');

        $product = Product::query()->findOrFail($dados['product_id']);
        if (! ProductStockDisplay::controlaEstoque($product)) {
            return response()->json([
                'message' => 'Este produto não usa controlo de stock.',
            ], 422);
        }

        $result = DB::transaction(function () use ($dados, $product) {
            $quantity = (float) $dados['quantity'];
            $unitCost = (float) $dados['unit_cost'];
            $total = $quantity * $unitCost;

            $purchase = Purchase::query()->create([
                'id' => (string) Str::uuid(),
                'fornecedor' => $dados['supplier'] ?? 'Reposição API',
                'total' => $total,
                'data' => now(),
                'itens' => [[
                    'product_id' => $product->id,
                    'nome' => $product->nome,
                    'quantidade' => $quantity,
                    'custo_unitario' => $unitCost,
                    'total' => $total,
                    'tipo' => 'STOCK_RELOAD',
                    'note' => $dados['note'] ?? null,
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
            $balance->quantity = (float) $balance->quantity + $quantity;
            $balance->save();

            StockMovement::query()->create([
                'id' => (string) Str::uuid(),
                'product_id' => $product->id,
                'from_location_id' => null,
                'to_location_id' => $dados['to_location_id'],
                'type' => 'IN',
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'reference_type' => 'STOCK_RELOAD',
                'reference_id' => $purchase->id,
                'note' => $dados['note'] ?? 'Recarregamento via API',
                'performed_by' => auth('api')->id(),
            ]);

            ProductStockDisplay::sincronizarStockGlobal($product->id);
            $product->preco_compra = $unitCost;
            $product->save();

            return ['product' => $product->fresh(), 'balance' => $balance];
        });

        return response()->json([
            'message' => 'Stock recarregado com sucesso.',
            'data' => [
                'product_id' => $result['product']->id,
                'location_id' => $result['balance']->location_id,
                'novo_stock_local' => (float) $result['balance']->quantity,
                'novo_stock_global' => ProductStockDisplay::stockParaExibicao($result['product']),
            ],
        ]);
    }

    public function balance(Request $request)
    {
        $dados = $request->validate([
            'location_id' => ['required', 'uuid', 'exists:stock_locations,id'],
            'product_id' => ['required', 'uuid', 'exists:products,id'],
        ]);

        if (! ProductStockDisplay::localizacaoEstaActiva($dados['location_id'])) {
            return response()->json([
                'data' => [
                    'location_id' => $dados['location_id'],
                    'product_id' => $dados['product_id'],
                    'quantity' => 0.0,
                ],
            ]);
        }

        $balance = StockBalance::query()
            ->where('location_id', $dados['location_id'])
            ->where('product_id', $dados['product_id'])
            ->first();

        $produto = Product::query()->find($dados['product_id']);

        return response()->json([
            'data' => [
                'location_id' => $dados['location_id'],
                'product_id' => $dados['product_id'],
                'quantity' => $produto
                    ? ProductStockDisplay::quantidadeParaVenda($produto, $dados['location_id'], $balance)
                    : 0.0,
            ],
        ]);
    }

    public function availability(Request $request)
    {
        $dados = $request->validate([
            'location_id' => ['required', 'uuid', 'exists:stock_locations,id'],
            'product_ids' => ['required', 'string'],
        ]);

        $productIds = collect(explode(',', $dados['product_ids']))
            ->map(fn (string $id) => trim($id))
            ->filter(fn (string $id) => $id !== '')
            ->unique()
            ->values()
            ->all();

        if ($productIds === []) {
            return response()->json(['data' => []]);
        }

        if (count($productIds) > 100) {
            return response()->json([
                'message' => 'Limite de 100 produtos por consulta de stock.',
            ], 422);
        }

        $localActiva = ProductStockDisplay::localizacaoEstaActiva($dados['location_id']);

        $balances = $localActiva
            ? StockBalance::query()
                ->where('location_id', $dados['location_id'])
                ->whereIn('product_id', $productIds)
                ->get(['product_id', 'quantity', 'updated_at'])
            : collect();

        $porProduto = $balances->keyBy('product_id');
        $produtos = Product::query()->whereIn('id', $productIds)->get();

        $data = [];
        foreach ($productIds as $productId) {
            $balance = $porProduto->get($productId);
            $produto = $produtos->firstWhere('id', $productId);

            $data[$productId] = [
                'quantity' => $produto
                    ? ProductStockDisplay::quantidadeParaVenda($produto, $dados['location_id'], $balance)
                    : 0.0,
                'version' => optional($balance?->updated_at)->toJSON(),
            ];
        }

        return response()->json(['data' => $data]);
    }

    public function adjust(Request $request, StockAdjustmentService $stockAdjustmentService)
    {
        $dados = $request->validate([
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'location_id' => ['required', 'uuid', 'exists:stock_locations,id'],
            'delta' => ['required', 'numeric', 'not_in:0'],
            'note' => ['nullable', 'string', 'max:500'],
            'unit_cost' => ['nullable', 'numeric', 'gte:0'],
        ]);

        ProductStockDisplay::exigirLocalizacaoActiva($dados['location_id']);

        $product = Product::query()->findOrFail($dados['product_id']);
        if (! ProductStockDisplay::controlaEstoque($product)) {
            return response()->json([
                'message' => 'Este produto não usa controlo de stock.',
            ], 422);
        }

        $movement = $stockAdjustmentService->aplicar(
            $dados['product_id'],
            $dados['location_id'],
            (float) $dados['delta'],
            $dados['note'] ?? null,
            auth('api')->id(),
            isset($dados['unit_cost']) ? (float) $dados['unit_cost'] : null,
        );

        $balance = StockBalance::query()
            ->where('location_id', $dados['location_id'])
            ->where('product_id', $dados['product_id'])
            ->first();

        return response()->json([
            'message' => 'Stock ajustado com sucesso.',
            'data' => [
                'movement_id' => $movement->id,
                'novo_stock_local' => (float) ($balance?->quantity ?? 0),
            ],
        ]);
    }
}
