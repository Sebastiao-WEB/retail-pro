<?php

namespace App\Livewire\Admin;

use App\Models\Sale;
use Livewire\Component;
use Livewire\WithPagination;

class SalesPage extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $vendas = Sale::query()
            ->when($this->search !== '', function ($q) {
                $q->where(function ($inner) {
                    $inner->where('referencia', 'like', "%{$this->search}%")
                        ->orWhere('cliente', 'like', "%{$this->search}%")
                        ->orWhere('metodo_pagamento', 'like', "%{$this->search}%");
                });
            })
            ->latest('data')
            ->paginate(12);

        return view('livewire.admin.sales-page')
            ->layout('components.layouts.desktop', ['title' => 'Vendas | RetailPro'])
            ->with(['vendas' => $vendas]);
    }
}
