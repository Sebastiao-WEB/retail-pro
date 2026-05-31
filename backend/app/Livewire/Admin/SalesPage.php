<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ValidatesDateInput;
use App\Models\Register;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesPage extends Component
{
    use ValidatesDateInput;
    use WithPagination;

    public string $search = '';

    public string $registerFilter = '';

    public string $estadoFilter = '';

    public string $pagamentoFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public bool $detailModalOpen = false;

    public ?string $detailId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRegisterFilter(): void
    {
        $this->resetPage();
    }

    public function updatedEstadoFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPagamentoFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function limparFiltros(): void
    {
        $this->search = '';
        $this->registerFilter = '';
        $this->estadoFilter = '';
        $this->pagamentoFilter = '';
        $this->dateFrom = '';
        $this->dateTo = '';
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

    private function vendasQuery(): Builder
    {
        return Sale::query()
            ->with('register')
            ->when($this->search !== '', function ($q) {
                $q->where(function ($inner) {
                    $inner->where('referencia', 'like', "%{$this->search}%")
                        ->orWhere('cliente', 'like', "%{$this->search}%")
                        ->orWhere('metodo_pagamento', 'like', "%{$this->search}%")
                        ->orWhere('operador', 'like', "%{$this->search}%")
                        ->orWhere('caixa', 'like', "%{$this->search}%");
                });
            })
            ->when($this->registerFilter !== '', fn ($q) => $q->where('register_id', $this->registerFilter))
            ->when($this->estadoFilter !== '', fn ($q) => $q->where('estado', $this->estadoFilter))
            ->when($this->pagamentoFilter !== '', fn ($q) => $q->where('metodo_pagamento', $this->pagamentoFilter))
            ->when($this->dataValida($this->dateFrom), fn ($q) => $q->whereDate('data', '>=', $this->dateFrom))
            ->when($this->dataValida($this->dateTo), fn ($q) => $q->whereDate('data', '<=', $this->dateTo));
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(auth()->user()?->can('sales.view'), 403);

        $filename = 'vendas-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, [
                'Referência',
                'Cliente',
                'Caixa',
                'Operador',
                'Pagamento',
                'Estado',
                'Subtotal',
                'Desconto',
                'Total',
                'Data',
            ], ';');

            $this->vendasQuery()
                ->latest('data')
                ->chunk(200, function ($vendas) use ($handle) {
                    foreach ($vendas as $venda) {
                        fputcsv($handle, [
                            $venda->referencia,
                            $venda->cliente,
                            $venda->caixa ?? $venda->register?->name ?? '',
                            $venda->operador ?? '',
                            $venda->metodo_pagamento,
                            $venda->estado,
                            number_format((float) $venda->subtotal, 2, '.', ''),
                            number_format((float) $venda->desconto_aplicado, 2, '.', ''),
                            number_format((float) $venda->total, 2, '.', ''),
                            optional($venda->data)->format('Y-m-d H:i:s'),
                        ], ';');
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function render()
    {
        $vendas = $this->vendasQuery()
            ->latest('data')
            ->paginate(12);

        $detalhe = null;
        if ($this->detailId) {
            $detalhe = Sale::query()
                ->with(['itens', 'register', 'cashSession', 'user'])
                ->find($this->detailId);
        }

        $registers = Register::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);

        $totalFiltrado = (float) (clone $this->vendasQuery())->sum('total');

        return view('livewire.admin.sales-page')
            ->layout('components.layouts.desktop', ['title' => 'Vendas | RetailPro'])
            ->with([
                'vendas' => $vendas,
                'detalhe' => $detalhe,
                'registers' => $registers,
                'totalFiltrado' => $totalFiltrado,
            ]);
    }
}
