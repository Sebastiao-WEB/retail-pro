<?php

namespace App\Livewire\Admin;

use App\Models\Product;
use Livewire\Component;
use Livewire\WithPagination;

class ProductsPage extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $modalOpen = false;

    public bool $confirmDeleteOpen = false;

    public ?string $editingId = null;

    public ?string $deleteId = null;

    public string $nome = '';
    public string $codigo_barras = '';
    public string $categoria = '';
    public string $preco_compra = '0';
    public string $preco_venda = '0';
    public string $iva_tipo = 'ISENTO';
    public string $iva_percentual = '0';
    public string $iva_valor = '0';
    public string $stock = '0';
    public bool $is_active = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        abort_unless(auth()->user()?->can('products.manage'), 403);
        $this->resetForm();
        $this->modalOpen = true;
    }

    public function openEditModal(string $id): void
    {
        abort_unless(auth()->user()?->can('products.manage'), 403);
        $produto = Product::query()->findOrFail($id);
        $this->editingId = $produto->id;
        $this->nome = $produto->nome;
        $this->codigo_barras = (string) ($produto->codigo_barras ?? '');
        $this->categoria = (string) ($produto->categoria ?? '');
        $this->preco_compra = (string) $produto->preco_compra;
        $this->preco_venda = (string) $produto->preco_venda;
        $this->iva_tipo = (string) ($produto->iva_tipo ?? 'ISENTO');
        $this->iva_percentual = (string) $produto->iva_percentual;
        $this->iva_valor = (string) $produto->iva_valor;
        $this->stock = (string) $produto->stock;
        $this->is_active = (bool) $produto->is_active;
        $this->modalOpen = true;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('products.manage'), 403);
        $dados = $this->validate([
            'nome' => ['required', 'string', 'max:255'],
            'codigo_barras' => ['nullable', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:255'],
            'preco_compra' => ['required', 'numeric'],
            'preco_venda' => ['required', 'numeric'],
            'iva_tipo' => ['required', 'in:ISENTO,PERCENTUAL,MONETARIO'],
            'iva_percentual' => ['required_if:iva_tipo,PERCENTUAL', 'nullable', 'numeric', 'gte:0'],
            'iva_valor' => ['required_if:iva_tipo,MONETARIO', 'nullable', 'numeric', 'gte:0'],
            'stock' => ['required', 'numeric'],
            'is_active' => ['boolean'],
        ]);

        $ivaTipo = $dados['iva_tipo'];
        $ivaPercentual = $ivaTipo === 'PERCENTUAL' ? (float) ($dados['iva_percentual'] ?? 0) : 0.0;
        $ivaValor = $ivaTipo === 'MONETARIO' ? (float) ($dados['iva_valor'] ?? 0) : 0.0;

        if ($ivaTipo === 'PERCENTUAL' && $ivaPercentual <= 0) {
            $this->addError('iva_percentual', 'Informe o percentual de IVA maior que zero.');
            return;
        }

        if ($ivaTipo === 'MONETARIO' && $ivaValor <= 0) {
            $this->addError('iva_valor', 'Informe o valor monetário de IVA maior que zero.');
            return;
        }

        Product::query()->updateOrCreate(
            ['id' => $this->editingId],
            [
                ...$dados,
                'iva_tipo' => $ivaTipo,
                'iva_valor' => $ivaValor,
                'iva_percentual' => $ivaPercentual,
            ]
        );

        session()->flash('toast', ['type' => 'success', 'message' => $this->editingId ? 'Produto atualizado.' : 'Produto criado.']);
        $this->closeModal();
    }

    public function confirmDelete(string $id): void
    {
        abort_unless(auth()->user()?->can('products.manage'), 403);
        $this->deleteId = $id;
        $this->confirmDeleteOpen = true;
    }

    public function delete(): void
    {
        abort_unless(auth()->user()?->can('products.manage'), 403);
        if (! $this->deleteId) {
            return;
        }

        $produto = Product::query()->find($this->deleteId);
        if ($produto) {
            $produto->update(['is_active' => false]);
            $produto->delete();
        }

        $this->confirmDeleteOpen = false;
        $this->deleteId = null;
        session()->flash('toast', ['type' => 'success', 'message' => 'Produto removido.']);
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
        $this->codigo_barras = '';
        $this->categoria = '';
        $this->preco_compra = '0';
        $this->preco_venda = '0';
        $this->iva_tipo = 'ISENTO';
        $this->iva_percentual = '0';
        $this->iva_valor = '0';
        $this->stock = '0';
        $this->is_active = true;
    }

    public function render()
    {
        $produtos = Product::query()
            ->when($this->search !== '', function ($q) {
                $q->where(function ($inner) {
                    $inner->where('nome', 'like', "%{$this->search}%")
                        ->orWhere('codigo_barras', 'like', "%{$this->search}%")
                        ->orWhere('categoria', 'like', "%{$this->search}%");
                });
            })
            ->latest()
            ->paginate(10);

        return view('livewire.admin.products-page')
            ->layout('components.layouts.desktop', ['title' => 'Produtos | RetailPro'])
            ->with(['produtos' => $produtos]);
    }
}
