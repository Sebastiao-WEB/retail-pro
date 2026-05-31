<?php

namespace App\Livewire\Admin;

use App\Models\StockLocation;
use App\Models\StockMovement;
use Livewire\Component;
use Livewire\WithPagination;

class StockMovementsPage extends Component
{
    use WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public string $locationFilter = '';

    public bool $reloadsOnly = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedLocationFilter(): void
    {
        $this->resetPage();
    }

    public function updatedReloadsOnly(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('stock.movements.view'), 403);

        $movements = StockMovement::query()
            ->with(['product', 'fromLocation', 'toLocation', 'performedBy', 'reloadRecord'])
            ->when($this->reloadsOnly, fn ($q) => $q->stockReloads())
            ->when($this->search !== '', function ($q) {
                $q->whereHas('product', function ($productQuery) {
                    $productQuery
                        ->where('nome', 'like', "%{$this->search}%")
                        ->orWhere('codigo_barras', 'like', "%{$this->search}%");
                });
            })
            ->when($this->typeFilter !== '', fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->locationFilter !== '', function ($q) {
                $q->where(function ($inner) {
                    $inner->where('from_location_id', $this->locationFilter)
                        ->orWhere('to_location_id', $this->locationFilter);
                });
            })
            ->latest()
            ->paginate(10);

        return view('livewire.admin.stock-movements-page')
            ->layout('components.layouts.desktop', ['title' => __('pages.titles.stock_movements')])
            ->with([
                'movements' => $movements,
                'locations' => StockLocation::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            ]);
    }
}
