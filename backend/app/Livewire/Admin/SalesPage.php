<?php

namespace App\Livewire\Admin;

use App\Models\Sale;
use Livewire\Component;
use Livewire\WithPagination;

class SalesPage extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $detailModalOpen = false;

    public ?string $detailId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openDetail(string $id): void
    {
        $this->detailId = $id;
        $this->detailModalOpen = true;
    }

    public function closeDetail(): void
    {
        $this->detailModalOpen = false;
        $this->detailId = null;
    }

    public function render()
    {
        $vendas = Sale::query()
            ->when($this->search !== '', function ($q) {
                $q->where(function ($inner) {
                    $inner->where('referencia', 'like', "%{$this->search}%")
                        ->orWhere('cliente', 'like', "%{$this->search}%")
                        ->orWhere('metodo_pagamento', 'like', "%{$this->search}%")
                        ->orWhere('operador', 'like', "%{$this->search}%")
                        ->orWhere('caixa', 'like', "%{$this->search}%");
                });
            })
            ->latest('data')
            ->paginate(12);

        $detalhe = null;
        if ($this->detailId) {
            $detalhe = Sale::query()
                ->with(['itens', 'register', 'cashSession', 'user'])
                ->find($this->detailId);
        }

        return view('livewire.admin.sales-page')
            ->layout('components.layouts.desktop', ['title' => 'Vendas | RetailPro'])
            ->with([
                'vendas' => $vendas,
                'detalhe' => $detalhe,
            ]);
    }
}
