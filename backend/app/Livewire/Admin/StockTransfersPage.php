<?php

namespace App\Livewire\Admin;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class StockTransfersPage extends Component
{
    use WithPagination;

    public bool $modalOpen = false;
    public string $from_location_id = '';
    public string $to_location_id = '';
    public string $product_id = '';
    public string $quantity = '1';
    public string $note = '';

    public function openModal(): void
    {
        abort_unless(auth()->user()?->can('stock.transfers.manage'), 403);
        $this->resetForm();
        $this->modalOpen = true;
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->from_location_id = '';
        $this->to_location_id = '';
        $this->product_id = '';
        $this->quantity = '1';
        $this->note = '';
    }

    public function createTransfer(): void
    {
        abort_unless(auth()->user()?->can('stock.transfers.manage'), 403);

        $dados = $this->validate([
            'from_location_id' => ['required', 'uuid', 'exists:stock_locations,id'],
            'to_location_id' => ['required', 'uuid', 'exists:stock_locations,id', 'different:from_location_id'],
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

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
                'note' => $dados['note'] ?: null,
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

        $this->closeModal();
        session()->flash('toast', ['type' => 'success', 'message' => 'Transferência concluída com sucesso.']);
    }

    public function render()
    {
        $transfers = StockTransfer::query()
            ->with(['items', 'fromLocation', 'toLocation'])
            ->latest('requested_at')
            ->paginate(12);

        return view('livewire.admin.stock-transfers-page')
            ->layout('components.layouts.desktop', ['title' => 'Transferências de Stock | RetailPro'])
            ->with([
                'transfers' => $transfers,
                'locations' => StockLocation::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
                'products' => Product::query()->where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
            ]);
    }
}
