<?php

namespace App\Livewire\Admin;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockBalance;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class StockReloadPage extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $reloadModalOpen = false;
    public ?string $productId = null;
    public string $productName = '';
    public string $quantity = '1';
    public string $unitCost = '0';
    public string $supplier = 'Reposição Manual';
    public string $note = '';
    public ?string $to_location_id = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openReloadModal(string $id): void
    {
        abort_unless(auth()->user()?->can('stock.reload'), 403);
        $product = Product::query()->findOrFail($id);
        $this->productId = $product->id;
        $this->productName = $product->nome;
        $this->quantity = '1';
        $this->unitCost = (string) $product->preco_compra;
        $this->supplier = 'Reposição Manual';
        $this->note = '';
        $this->to_location_id = StockLocation::query()
            ->where('is_active', true)
            ->orderBy('is_saleable', 'desc')
            ->orderBy('name')
            ->value('id');
        $this->reloadModalOpen = true;
    }

    public function applyReload(): void
    {
        abort_unless(auth()->user()?->can('stock.reload'), 403);

        $dados = $this->validate([
            'productId' => ['required', 'uuid', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unitCost' => ['required', 'numeric', 'gte:0'],
            'supplier' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
            'to_location_id' => ['required', 'uuid', 'exists:stock_locations,id'],
        ]);

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
                    'note' => $dados['note'] ?: null,
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
                'reference_type' => 'PURCHASE',
                'reference_id' => $purchase->id,
                'note' => $dados['note'] ?: 'Recarregamento manual de stock',
                'performed_by' => auth()->id(),
            ]);

            // cache global opcional de stock no produto
            $product->stock = (float) StockBalance::query()
                ->where('product_id', $product->id)
                ->sum('quantity');
            $product->preco_compra = $custoUnitario;
            $product->save();
        });

        $this->reloadModalOpen = false;
        session()->flash('toast', ['type' => 'success', 'message' => 'Stock recarregado com sucesso.']);
    }

    public function render()
    {
        $products = Product::query()
            ->where('is_active', true)
            ->when($this->search !== '', function ($q) {
                $q->where(fn ($inner) => $inner
                    ->where('nome', 'like', "%{$this->search}%")
                    ->orWhere('codigo_barras', 'like', "%{$this->search}%")
                    ->orWhere('categoria', 'like', "%{$this->search}%"));
            })
            ->orderBy('nome')
            ->paginate(12);

        return view('livewire.admin.stock-reload-page')
            ->layout('components.layouts.desktop', ['title' => 'Recarregar Stock | RetailPro'])
            ->with([
                'products' => $products,
                'locations' => StockLocation::query()
                    ->where('is_active', true)
                    ->orderBy('is_saleable', 'desc')
                    ->orderBy('name')
                    ->get(['id', 'name', 'code', 'type']),
            ]);
    }
}
