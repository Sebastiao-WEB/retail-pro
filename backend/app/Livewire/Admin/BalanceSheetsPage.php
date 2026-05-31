<?php

namespace App\Livewire\Admin;

use App\Models\BalanceSheet;
use App\Services\BalanceSheetBuilder;
use Livewire\Component;
use Livewire\WithPagination;

class BalanceSheetsPage extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $createModalOpen = false;

    public bool $editModalOpen = false;

    public ?string $editingId = null;

    public string $titulo = '';

    public string $data_referencia = '';

    public string $periodo_inicio = '';

    public string $periodo_fim = '';

    public string $notas = '';

    public function mount(): void
    {
        $this->data_referencia = now()->toDateString();
        $this->periodo_inicio = now()->startOfMonth()->toDateString();
        $this->periodo_fim = now()->toDateString();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        abort_unless(auth()->user()?->can('balance_sheets.manage'), 403);
        $this->resetForm();
        $this->createModalOpen = true;
    }

    public function criar(BalanceSheetBuilder $builder): void
    {
        abort_unless(auth()->user()?->can('balance_sheets.manage'), 403);

        $dados = $this->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'data_referencia' => ['required', 'date'],
            'periodo_inicio' => ['required', 'date'],
            'periodo_fim' => ['required', 'date', 'after_or_equal:periodo_inicio'],
            'notas' => ['nullable', 'string'],
        ]);

        $balance = $builder->create($dados, auth()->id());
        $this->createModalOpen = false;
        $this->openEditModal($balance->id);
        session()->flash('toast', ['type' => 'success', 'message' => 'Balanço calculado com recargas, vendas e stock do período.']);
    }

    public function openEditModal(string $id): void
    {
        abort_unless(auth()->user()?->can('balance_sheets.view'), 403);

        $balance = BalanceSheet::query()->with(['lines.product', 'locationLines'])->findOrFail($id);
        $this->editingId = $balance->id;
        $this->titulo = $balance->titulo;
        $this->data_referencia = $balance->data_referencia->toDateString();
        $this->periodo_inicio = optional($balance->periodo_inicio)->toDateString() ?? '';
        $this->periodo_fim = optional($balance->periodo_fim)->toDateString() ?? '';
        $this->notas = (string) ($balance->notas ?? '');
        $this->editModalOpen = true;
    }

    public function closeEditModal(): void
    {
        $this->editModalOpen = false;
        $this->editingId = null;
    }

    public function recalcular(BalanceSheetBuilder $builder): void
    {
        abort_unless(auth()->user()?->can('balance_sheets.manage'), 403);

        $balance = $this->obterBalanceEmEdicao();
        if ($balance->isFinalized()) {
            session()->flash('toast', ['type' => 'error', 'message' => 'Balanço finalizado não pode ser alterado.']);

            return;
        }

        $builder->syncAutomaticLines($balance);
        $this->openEditModal($balance->id);
        session()->flash('toast', ['type' => 'success', 'message' => 'Balanço recalculado com dados actuais.']);
    }

    public function guardar(): void
    {
        abort_unless(auth()->user()?->can('balance_sheets.manage'), 403);

        $balance = $this->obterBalanceEmEdicao();
        if ($balance->isFinalized()) {
            return;
        }

        $this->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'notas' => ['nullable', 'string'],
        ]);

        $balance->update([
            'titulo' => $this->titulo,
            'notas' => $this->notas ?: null,
        ]);

        session()->flash('toast', ['type' => 'success', 'message' => 'Balanço actualizado.']);
    }

    public function finalizar(): void
    {
        abort_unless(auth()->user()?->can('balance_sheets.manage'), 403);

        $balance = $this->obterBalanceEmEdicao();
        if ($balance->isFinalized()) {
            return;
        }

        $this->guardar();

        $balance->update([
            'status' => 'FINALIZED',
            'finalized_at' => now(),
        ]);

        $this->closeEditModal();
        session()->flash('toast', ['type' => 'success', 'message' => 'Balanço finalizado. Pode gerar o PDF.']);
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('balance_sheets.view'), 403);

        $balances = BalanceSheet::query()
            ->with('preparedBy')
            ->when($this->search !== '', function ($q) {
                $q->where(function ($inner) {
                    $inner->where('referencia', 'like', "%{$this->search}%")
                        ->orWhere('titulo', 'like', "%{$this->search}%");
                });
            })
            ->latest('data_referencia')
            ->paginate(10);

        $balanceEmEdicao = null;
        if ($this->editingId) {
            $balanceEmEdicao = BalanceSheet::query()->with(['lines.product', 'locationLines'])->find($this->editingId);
        }

        return view('livewire.admin.balance-sheets-page')
            ->layout('components.layouts.desktop', ['title' => 'Balanço de Fecho | RetailPro'])
            ->with([
                'balances' => $balances,
                'balanceEmEdicao' => $balanceEmEdicao,
            ]);
    }

    private function obterBalanceEmEdicao(): BalanceSheet
    {
        return BalanceSheet::query()->with('lines')->findOrFail($this->editingId);
    }

    private function resetForm(): void
    {
        $this->titulo = 'Balanço de fecho '.now()->format('d/m/Y');
        $this->data_referencia = now()->toDateString();
        $this->periodo_inicio = now()->startOfMonth()->toDateString();
        $this->periodo_fim = now()->toDateString();
        $this->notas = '';
    }
}
