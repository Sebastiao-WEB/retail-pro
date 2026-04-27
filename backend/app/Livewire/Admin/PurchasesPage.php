<?php

namespace App\Livewire\Admin;

use App\Models\Purchase;
use Livewire\Component;
use Livewire\WithPagination;

class PurchasesPage extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $modalOpen = false;
    public bool $confirmDeleteOpen = false;
    public ?string $editingId = null;
    public ?string $deleteId = null;

    public string $fornecedor = '';
    public string $total = '0';
    public string $data = '';
    public string $itens_texto = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        abort_unless(auth()->user()?->can('purchases.manage'), 403);
        $this->resetForm();
        $this->data = now()->format('Y-m-d\TH:i');
        $this->modalOpen = true;
    }

    public function openEditModal(string $id): void
    {
        abort_unless(auth()->user()?->can('purchases.manage'), 403);
        $compra = Purchase::query()->findOrFail($id);
        $this->editingId = $compra->id;
        $this->fornecedor = $compra->fornecedor;
        $this->total = (string) $compra->total;
        $this->data = optional($compra->data)->format('Y-m-d\TH:i') ?? '';
        $this->itens_texto = json_encode($compra->itens ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $this->modalOpen = true;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('purchases.manage'), 403);
        $dados = $this->validate([
            'fornecedor' => ['required', 'string', 'max:255'],
            'total' => ['required', 'numeric'],
            'data' => ['required', 'date'],
            'itens_texto' => ['nullable', 'string'],
        ]);

        $itens = [];
        if (trim($dados['itens_texto']) !== '') {
            $decoded = json_decode($dados['itens_texto'], true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                $this->addError('itens_texto', 'JSON inválido no campo itens.');
                return;
            }
            $itens = $decoded;
        }

        Purchase::query()->updateOrCreate(
            ['id' => $this->editingId],
            [
                'fornecedor' => $dados['fornecedor'],
                'total' => $dados['total'],
                'data' => $dados['data'],
                'itens' => $itens,
            ]
        );

        session()->flash('toast', ['type' => 'success', 'message' => $this->editingId ? 'Compra atualizada.' : 'Compra criada.']);
        $this->closeModal();
    }

    public function confirmDelete(string $id): void
    {
        abort_unless(auth()->user()?->can('purchases.manage'), 403);
        $this->deleteId = $id;
        $this->confirmDeleteOpen = true;
    }

    public function delete(): void
    {
        abort_unless(auth()->user()?->can('purchases.manage'), 403);
        if ($this->deleteId) {
            Purchase::query()->where('id', $this->deleteId)->delete();
        }
        $this->confirmDeleteOpen = false;
        $this->deleteId = null;
        session()->flash('toast', ['type' => 'success', 'message' => 'Compra removida.']);
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->fornecedor = '';
        $this->total = '0';
        $this->data = '';
        $this->itens_texto = '';
    }

    public function render()
    {
        $compras = Purchase::query()
            ->when($this->search !== '', function ($q) {
                $q->where('fornecedor', 'like', "%{$this->search}%");
            })
            ->latest('data')
            ->paginate(10);

        return view('livewire.admin.purchases-page')
            ->layout('components.layouts.desktop', ['title' => 'Compras | RetailPro'])
            ->with(['compras' => $compras]);
    }
}
