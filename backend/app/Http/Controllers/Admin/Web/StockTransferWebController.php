<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Web\Concerns\AuthorizesAdminWeb;
use App\Http\Controllers\Admin\Web\Concerns\RespondsAsJson;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Services\StockByLocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockTransferWebController extends Controller
{
    use AuthorizesAdminWeb;
    use RespondsAsJson;

    public function index(Request $request)
    {
        $this->authorizeAdmin('stock.transfers.view');

        $transfers = StockTransfer::query()
            ->with(['items', 'fromLocation', 'toLocation'])
            ->latest('requested_at')
            ->paginate(12);

        return view('admin.stock-transfers.index', [
            'transfers' => $transfers,
            'locations' => StockLocation::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'products' => Product::query()->where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
            'canManage' => auth()->user()?->can('stock.transfers.manage') ?? false,
        ]);
    }

    public function available(Request $request, StockByLocationService $stockService)
    {
        $this->authorizeAdmin('stock.transfers.view');

        $request->validate([
            'from_location_id' => ['required', 'uuid', 'exists:stock_locations,id'],
            'product_id' => ['required', 'uuid', 'exists:products,id'],
        ]);

        $disponivel = $stockService->quantidadeDisponivel(
            $request->string('from_location_id')->toString(),
            $request->string('product_id')->toString()
        );

        return $this->jsonOk(['disponivel' => $disponivel]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin('stock.transfers.manage');

        try {
            $dados = $request->validate([
                'from_location_id' => ['required', 'uuid', 'exists:stock_locations,id'],
                'to_location_id' => ['required', 'uuid', 'exists:stock_locations,id', 'different:from_location_id'],
                'product_id' => ['required', 'uuid', 'exists:products,id'],
                'quantity' => ['required', 'numeric', 'gt:0'],
                'note' => ['nullable', 'string', 'max:500'],
            ]);
        } catch (ValidationException $exception) {
            return $this->jsonFromValidation($exception);
        }

        try {
            DB::transaction(function () use ($dados) {
                $product = Product::query()->findOrFail($dados['product_id']);
                $quantidade = (float) $dados['quantity'];

                $origem = StockBalance::query()
                    ->where('location_id', $dados['from_location_id'])
                    ->where('product_id', $product->id)
                    ->first();

                if (! $origem || (float) $origem->quantity < $quantidade) {
                    throw ValidationException::withMessages([
                        'quantity' => ['Quantidade indisponível para transferência na localização de origem.'],
                    ]);
                }

                $destino = StockBalance::query()->firstOrCreate(
                    ['location_id' => $dados['to_location_id'], 'product_id' => $product->id],
                    ['id' => (string) Str::uuid(), 'quantity' => 0]
                );

                $transfer = StockTransfer::query()->create([
                    'id' => (string) Str::uuid(),
                    'from_location_id' => $dados['from_location_id'],
                    'to_location_id' => $dados['to_location_id'],
                    'requested_by' => auth()->id(),
                    'status' => 'COMPLETED',
                    'note' => $dados['note'] ?? null,
                    'requested_at' => now(),
                    'completed_at' => now(),
                ]);

                StockTransferItem::query()->create([
                    'id' => (string) Str::uuid(),
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $product->id,
                    'product_name_snapshot' => $product->nome,
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
                    'product_id' => $product->id,
                    'from_location_id' => $dados['from_location_id'],
                    'to_location_id' => $dados['to_location_id'],
                    'type' => 'TRANSFER',
                    'quantity' => $quantidade,
                    'unit_cost' => (float) $product->preco_compra,
                    'reference_type' => 'STOCK_TRANSFER',
                    'reference_id' => $transfer->id,
                    'note' => $dados['note'] ?: 'Transferência interna de stock',
                    'performed_by' => auth()->id(),
                ]);

                $product->stock = (float) StockBalance::query()
                    ->where('product_id', $product->id)
                    ->sum('quantity');
                $product->save();
            });
        } catch (ValidationException $exception) {
            return $this->jsonFromValidation($exception);
        }

        return $this->jsonOk(null, __('toasts.transfer_completed'));
    }
}
