<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockTransferController extends Controller
{
    public function index()
    {
        $transfers = StockTransfer::query()
            ->with(['items'])
            ->latest('requested_at')
            ->limit(200)
            ->get()
            ->map(fn (StockTransfer $transfer) => [
                'id' => $transfer->id,
                'fromLocationId' => $transfer->from_location_id,
                'toLocationId' => $transfer->to_location_id,
                'requestedBy' => $transfer->requested_by,
                'status' => $transfer->status,
                'note' => $transfer->note,
                'requestedAt' => optional($transfer->requested_at)->toISOString(),
                'completedAt' => optional($transfer->completed_at)->toISOString(),
                'items' => $transfer->items->map(fn (StockTransferItem $item) => [
                    'productId' => $item->product_id,
                    'productName' => $item->product_name_snapshot,
                    'quantityRequested' => (float) $item->quantity_requested,
                    'quantitySent' => $item->quantity_sent !== null ? (float) $item->quantity_sent : null,
                    'quantityReceived' => $item->quantity_received !== null ? (float) $item->quantity_received : null,
                ])->values(),
            ]);

        return response()->json(['data' => $transfers]);
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'from_location_id' => ['required', 'uuid', 'exists:stock_locations,id'],
            'to_location_id' => ['required', 'uuid', 'exists:stock_locations,id', 'different:from_location_id'],
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $payload = DB::transaction(function () use ($dados) {
            $produto = Product::query()->findOrFail($dados['product_id']);
            $quantidade = (float) $dados['quantity'];

            $origem = StockBalance::query()
                ->where('location_id', $dados['from_location_id'])
                ->where('product_id', $produto->id)
                ->first();

            if (! $origem || (float) $origem->quantity < $quantidade) {
                throw ValidationException::withMessages([
                    'quantity' => ['Quantidade indisponível para transferência na localização de origem.'],
                ]);
            }

            $destino = StockBalance::query()->firstOrCreate(
                ['location_id' => $dados['to_location_id'], 'product_id' => $produto->id],
                ['id' => (string) Str::uuid(), 'quantity' => 0]
            );

            $transfer = StockTransfer::query()->create([
                'id' => (string) Str::uuid(),
                'from_location_id' => $dados['from_location_id'],
                'to_location_id' => $dados['to_location_id'],
                'requested_by' => auth('api')->id(),
                'status' => 'COMPLETED',
                'note' => $dados['note'] ?? null,
                'requested_at' => now(),
                'completed_at' => now(),
            ]);

            StockTransferItem::query()->create([
                'id' => (string) Str::uuid(),
                'stock_transfer_id' => $transfer->id,
                'product_id' => $produto->id,
                'product_name_snapshot' => $produto->nome,
                'quantity_requested' => $quantidade,
                'quantity_sent' => $quantidade,
                'quantity_received' => $quantidade,
            ]);

            $origem->quantity = (float) $origem->quantity - $quantidade;
            $origem->save();

            $destino->quantity = (float) $destino->quantity + $quantidade;
            $destino->save();

            StockMovement::query()->create([
                'id' => (string) Str::uuid(),
                'product_id' => $produto->id,
                'from_location_id' => $dados['from_location_id'],
                'to_location_id' => $dados['to_location_id'],
                'type' => 'TRANSFER',
                'quantity' => $quantidade,
                'unit_cost' => (float) $produto->preco_compra,
                'reference_type' => 'STOCK_TRANSFER',
                'reference_id' => $transfer->id,
                'note' => $dados['note'] ?? 'Transferência de stock',
                'performed_by' => auth('api')->id(),
            ]);

            $produto->stock = (float) StockBalance::query()
                ->where('product_id', $produto->id)
                ->sum('quantity');
            $produto->save();

            return $transfer;
        });

        return response()->json([
            'message' => 'Transferência realizada com sucesso.',
            'data' => ['id' => $payload->id, 'status' => $payload->status],
        ], 201);
    }
}
