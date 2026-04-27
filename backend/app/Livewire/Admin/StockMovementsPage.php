<?php

namespace App\Livewire\Admin;

use App\Models\Product;
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

    public function render()
    {
        $movements = StockMovement::query()
            ->with(['product'])
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
            ->paginate(15);

        return view('livewire.admin.stock-movements-page')
            ->layout('components.layouts.desktop', ['title' => 'Movimentos de Stock | RetailPro'])
            ->with([
                'movements' => $movements,
                'locations' => StockLocation::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
                'products' => Product::query()->where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
            ]);
    }
}
