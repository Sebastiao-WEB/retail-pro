<?php

namespace App\Livewire\Admin;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockBalance;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Services\StockAdjustmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class StockReloadPage extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $reloadModalOpen = false;
    public bool $adjustModalOpen = false;
    public ?string $productId = null;
    public string $productName = '';
    public string $quantity = '1';
    public string $unitCost = '0';
    public string $supplier = 'Reposição Manual';
    public string $note = '';
    public ?string $to_location_id = null;
    public string $adjustmentDelta = '';

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
        $this->adjustModalOpen = false;
        $this->reloadModalOpen = true;
    }

    public function openAdjustModal(string $id): void
    {
        abort_unless(auth()->user()?->can('stock.reload'), 403);
        $product = Product::query()->findOrFail($id);
        $this->productId = $product->id;
        $this->productName = $product->nome;
        $this->adjustmentDelta = '';
        $this->unitCost = (string) $product->preco_compra;
        $this->note = '';
        $this->to_location_id = StockLocation::query()
            ->where('is_active', true)
            ->orderBy('is_saleable', 'desc')
            ->orderBy('name')
            ->value('id');
        $this->reloadModalOpen = false;
        $this->adjustModalOpen = true;
    }

    #[Computed]
    public function saldoNaLocalizacao(): float
    {
        if (! $this->productId || ! $this->to_location_id) {
            return 0.0;
        }

        return (float) (StockBalance::query()
            ->where('location_id', $this->to_location_id)
            ->where('product_id', $this->productId)
            ->value('quantity') ?? 0);
    }

    public function applyAdjustment(): void
    {
        abort_unless(auth()->user()?->can('stock.reload'), 403);

        try {
            $dados = $this->validate([
                'productId' => ['required', 'uuid', 'exists:products,id'],
                'to_location_id' => ['required', 'uuid', 'exists:stock_locations,id'],
                'adjustmentDelta' => ['required', 'numeric', 'not_in:0'],
                'note' => ['nullable', 'string', 'max:500'],
            ]);

            app(StockAdjustmentService::class)->aplicar(
                $dados['productId'],
                $dados['to_location_id'],
                (float) $dados['adjustmentDelta'],
                $dados['note'] ?: null,
                auth()->id(),
                (float) $this->unitCost,
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $campo => $mensagens) {
                $alvo = $campo === 'delta' ? 'adjustmentDelta' : $campo;
                $this->addError($alvo, $mensagens[0] ?? '');
            }
            $this->dispatch('rp-focus-field', field: 'adjustmentDelta');

            return;
        }

        $this->adjustModalOpen = false;
        session()->flash('toast', ['type' => 'success', 'message' => __('toasts.stock_adjusted')]);
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
                'reference_type' => 'STOCK_RELOAD',
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
        session()->flash('toast', ['type' => 'success', 'message' => __('toasts.stock_reloaded')]);
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('stock.reload'), 403);

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

        $reloadHistory = StockMovement::query()
            ->stockReloads()
            ->with(['product', 'toLocation', 'performedBy', 'reloadRecord'])
            ->latest()
            ->paginate(10, ['*'], 'historyPage');

        return view('livewire.admin.stock-reload-page')
            ->layout('components.layouts.desktop', ['title' => __('pages.titles.stock_reload')])
            ->with([
                'products' => $products,
                'reloadHistory' => $reloadHistory,
                'locations' => StockLocation::query()
                    ->where('is_active', true)
                    ->orderBy('is_saleable', 'desc')
                    ->orderBy('name')
                    ->get(['id', 'name', 'code', 'type']),
            ]);
    }
}
