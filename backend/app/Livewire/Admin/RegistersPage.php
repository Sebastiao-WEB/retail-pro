<?php

namespace App\Livewire\Admin;

use App\Models\Register;
use Livewire\Component;
use Livewire\WithPagination;

class RegistersPage extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $modalOpen = false;
    public bool $confirmDeleteOpen = false;
    public ?string $editingId = null;
    public ?string $deleteId = null;

    public string $code = '';
    public string $name = '';
    public bool $is_active = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        abort_unless(auth()->user()?->can('registers.manage'), 403);
        $this->resetForm();
        $this->modalOpen = true;
    }

    public function openEditModal(string $id): void
    {
        abort_unless(auth()->user()?->can('registers.manage'), 403);
        $register = Register::query()->findOrFail($id);
        $this->editingId = $register->id;
        $this->code = $register->code;
        $this->name = $register->name;
        $this->is_active = (bool) $register->is_active;
        $this->modalOpen = true;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('registers.manage'), 403);
        $dados = $this->validate([
            'code' => ['required', 'string', 'max:255', 'unique:registers,code,'.($this->editingId ?? 'NULL').',id'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        Register::query()->updateOrCreate(['id' => $this->editingId], $dados);

        session()->flash('toast', ['type' => 'success', 'message' => $this->editingId ? 'Caixa atualizado.' : 'Caixa criado.']);
        $this->closeModal();
    }

    public function confirmDelete(string $id): void
    {
        abort_unless(auth()->user()?->can('registers.manage'), 403);
        $this->deleteId = $id;
        $this->confirmDeleteOpen = true;
    }

    public function delete(): void
    {
        abort_unless(auth()->user()?->can('registers.manage'), 403);
        if ($this->deleteId) {
            Register::query()->where('id', $this->deleteId)->update(['is_active' => false]);
        }
        $this->confirmDeleteOpen = false;
        $this->deleteId = null;
        session()->flash('toast', ['type' => 'success', 'message' => 'Caixa desativado.']);
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->is_active = true;
    }

    public function render()
    {
        $registers = Register::query()
            ->when($this->search !== '', function ($q) {
                $q->where(fn ($inner) => $inner
                    ->where('code', 'like', "%{$this->search}%")
                    ->orWhere('name', 'like', "%{$this->search}%"));
            })
            ->latest()
            ->paginate(10);

        return view('livewire.admin.registers-page')
            ->layout('components.layouts.desktop', ['title' => 'Caixas | RetailPro'])
            ->with(['registers' => $registers]);
    }
}
