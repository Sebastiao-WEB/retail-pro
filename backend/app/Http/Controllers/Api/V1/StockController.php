<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockBalance;
use App\Models\StockMovement;
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

        $result = DB::transaction(function () use ($dados) {
            $product = Product::query()->findOrFail($dados['product_id']);
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
                'reference_type' => 'PURCHASE',
                'reference_id' => $purchase->id,
                'note' => $dados['note'] ?? 'Recarregamento via API',
                'performed_by' => auth('api')->id(),
            ]);

            $product->stock = (float) StockBalance::query()
                ->where('product_id', $product->id)
                ->sum('quantity');
            $product->preco_compra = $unitCost;
            $product->save();

            return ['product' => $product, 'balance' => $balance];
        });

        return response()->json([
            'message' => 'Stock recarregado com sucesso.',
            'data' => [
                'product_id' => $result['product']->id,
                'location_id' => $result['balance']->location_id,
                'novo_stock_local' => (float) $result['balance']->quantity,
                'novo_stock_global' => (float) $result['product']->stock,
            ],
        ]);
    }
}
