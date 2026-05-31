<?php

namespace App\Livewire\Admin;

use App\Models\Register;
use App\Models\StockBalance;
use App\Models\StockLocation;
use App\Services\StockByLocationService;
use Livewire\Component;
use Livewire\WithPagination;

class StockLocationsPage extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $modalOpen = false;
    public bool $confirmDeleteOpen = false;
    public bool $stockModalOpen = false;
    public ?string $editingId = null;
    public ?string $deleteId = null;
    public ?string $stockLocationId = null;

    public ?string $register_id = null;
    public string $code = '';
    public string $name = '';
    public string $type = 'STORE_FLOOR';
    public bool $is_saleable = true;
    public bool $is_active = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        abort_unless(auth()->user()?->can('stock_locations.manage'), 403);
        $this->resetForm();
        $this->modalOpen = true;
    }

    public function openEditModal(string $id): void
    {
        abort_unless(auth()->user()?->can('stock_locations.manage'), 403);
        $location = StockLocation::query()->findOrFail($id);
        $this->editingId = $location->id;
        $this->register_id = $location->register_id;
        $this->code = $location->code;
        $this->name = $location->name;
        $this->type = $location->type;
        $this->is_saleable = (bool) $location->is_saleable;
        $this->is_active = (bool) $location->is_active;
        $this->modalOpen = true;
    }

    public function openStockModal(string $id): void
    {
        abort_unless(auth()->user()?->can('stock_locations.view'), 403);
        $this->stockLocationId = $id;
        $this->stockModalOpen = true;
    }

    public function closeStockModal(): void
    {
        $this->stockModalOpen = false;
        $this->stockLocationId = null;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('stock_locations.manage'), 403);
        $dados = $this->validate([
            'register_id' => ['nullable', 'uuid', 'exists:registers,id'],
            'code' => ['required', 'string', 'max:255', 'unique:stock_locations,code,'.($this->editingId ?? 'NULL').',id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:STORE_FLOOR,WAREHOUSE,DAMAGE,RETURN_AREA,TRANSIT'],
            'is_saleable' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        StockLocation::query()->updateOrCreate(['id' => $this->editingId], $dados);
        session()->flash('toast', ['type' => 'success', 'message' => $this->editingId ? __('toasts.location_updated') : __('toasts.location_created')]);
        $this->closeModal();
    }

    public function confirmDelete(string $id): void
    {
        abort_unless(auth()->user()?->can('stock_locations.manage'), 403);
        $this->deleteId = $id;
        $this->confirmDeleteOpen = true;
    }

    public function delete(): void
    {
        abort_unless(auth()->user()?->can('stock_locations.manage'), 403);
        if ($this->deleteId) {
            StockLocation::query()->where('id', $this->deleteId)->update(['is_active' => false]);
        }
        $this->confirmDeleteOpen = false;
        $this->deleteId = null;
        session()->flash('toast', ['type' => 'success', 'message' => __('toasts.location_disabled')]);
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->register_id = null;
        $this->code = '';
        $this->name = '';
        $this->type = 'STORE_FLOOR';
        $this->is_saleable = true;
        $this->is_active = true;
    }

    public function render(StockByLocationService $stockService)
    {
        abort_unless(auth()->user()?->can('stock_locations.view'), 403);

        $locations = StockLocation::query()
            ->with('register')
            ->withSum(['balances as total_quantity' => fn ($q) => $q->where('quantity', '>', 0)], 'quantity')
            ->withCount(['balances as products_count' => fn ($q) => $q->where('quantity', '>', 0)])
            ->when($this->search !== '', function ($q) {
                $q->where(fn ($inner) => $inner
                    ->where('code', 'like', "%{$this->search}%")
                    ->orWhere('name', 'like', "%{$this->search}%")
                    ->orWhere('type', 'like', "%{$this->search}%"));
            })
            ->orderBy('code')
            ->paginate(10);

        $stockDetalhe = null;
        if ($this->stockLocationId) {
            $resumo = $stockService->resumoPorLocalizacao($this->stockLocationId);
            $stockDetalhe = $resumo[0] ?? null;
        }

        return view('livewire.admin.stock-locations-page')
            ->layout('components.layouts.desktop', ['title' => __('pages.titles.stock_locations')])
            ->with([
                'locations' => $locations,
                'registers' => Register::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
                'stockDetalhe' => $stockDetalhe,
            ]);
    }
}
