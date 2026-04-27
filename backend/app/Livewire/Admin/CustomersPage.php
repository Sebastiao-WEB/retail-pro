<?php

namespace App\Livewire\Admin;

use App\Models\Customer;
use Livewire\Component;
use Livewire\WithPagination;

class CustomersPage extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $modalOpen = false;
    public bool $confirmDeleteOpen = false;
    public ?string $editingId = null;
    public ?string $deleteId = null;

    public string $nome = '';
    public string $telefone = '';
    public string $email = '';
    public string $nuit = '';
    public bool $is_active = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        abort_unless(auth()->user()?->can('customers.manage'), 403);
        $this->resetForm();
        $this->modalOpen = true;
    }

    public function openEditModal(string $id): void
    {
        abort_unless(auth()->user()?->can('customers.manage'), 403);
        $cliente = Customer::query()->findOrFail($id);
        $this->editingId = $cliente->id;
        $this->nome = $cliente->nome;
        $this->telefone = (string) ($cliente->telefone ?? '');
        $this->email = (string) ($cliente->email ?? '');
        $this->nuit = (string) ($cliente->nuit ?? '');
        $this->is_active = (bool) $cliente->is_active;
        $this->modalOpen = true;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('customers.manage'), 403);
        $dados = $this->validate([
            'nome' => ['required', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'nuit' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        Customer::query()->updateOrCreate(['id' => $this->editingId], $dados);
        session()->flash('toast', ['type' => 'success', 'message' => $this->editingId ? 'Cliente atualizado.' : 'Cliente criado.']);
        $this->closeModal();
    }

    public function confirmDelete(string $id): void
    {
        abort_unless(auth()->user()?->can('customers.manage'), 403);
        $this->deleteId = $id;
        $this->confirmDeleteOpen = true;
    }

    public function delete(): void
    {
        abort_unless(auth()->user()?->can('customers.manage'), 403);
        if (! $this->deleteId) {
            return;
        }

        $cliente = Customer::query()->find($this->deleteId);
        if ($cliente) {
            $cliente->update(['is_active' => false]);
        }

        $this->confirmDeleteOpen = false;
        $this->deleteId = null;
        session()->flash('toast', ['type' => 'success', 'message' => 'Cliente desativado.']);
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->nome = '';
        $this->telefone = '';
        $this->email = '';
        $this->nuit = '';
        $this->is_active = true;
    }

    public function render()
    {
        $clientes = Customer::query()
            ->when($this->search !== '', function ($q) {
                $q->where(function ($inner) {
                    $inner->where('nome', 'like', "%{$this->search}%")
                        ->orWhere('telefone', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%")
                        ->orWhere('nuit', 'like', "%{$this->search}%");
                });
            })
            ->latest()
            ->paginate(10);

        return view('livewire.admin.customers-page')
            ->layout('components.layouts.desktop', ['title' => 'Clientes | RetailPro'])
            ->with(['clientes' => $clientes]);
    }
}
