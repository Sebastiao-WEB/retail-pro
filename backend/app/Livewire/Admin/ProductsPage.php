<?php

namespace App\Livewire\Admin;

use App\Models\Product;
use App\Support\ProductValidation;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class ProductsPage extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $modalOpen = false;

    public ?string $editingId = null;

    public string $nome = '';
    public string $codigo_barras = '';
    public string $categoria = '';
    public string $preco_compra = '0';
    public string $preco_venda = '0';
    public string $iva_tipo = 'ISENTO';
    public string $iva_percentual = '0';
    public string $iva_valor = '0';
    public string $stockAtual = '0';
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
        $this->stockAtual = (string) $produto->stock;
        $this->is_active = (bool) $produto->is_active;
        $this->modalOpen = true;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('products.manage'), 403);

        try {
            $dados = $this->validate(
                $this->regrasValidacao(),
                [],
                $this->atributosValidacao()
            );
        } catch (ValidationException $exception) {
            $campo = array_key_first($exception->errors());
            if (is_string($campo) && $campo !== '') {
                $this->dispatch('rp-focus-field', field: $campo);
            }
            throw $exception;
        }

        $ivaTipo = $dados['iva_tipo'];
        $ivaPercentual = $ivaTipo === 'PERCENTUAL' ? (float) ($dados['iva_percentual'] ?? 0) : 0.0;
        $ivaValor = $ivaTipo === 'MONETARIO' ? (float) ($dados['iva_valor'] ?? 0) : 0.0;

        if ($ivaTipo === 'PERCENTUAL' && $ivaPercentual <= 0) {
            $this->addError('iva_percentual', __('pages.products.iva_percent_required'));
            $this->dispatch('rp-focus-field', field: 'iva_percentual');

            return;
        }

        if ($ivaTipo === 'MONETARIO' && $ivaValor <= 0) {
            $this->addError('iva_valor', __('pages.products.iva_amount_required'));
            $this->dispatch('rp-focus-field', field: 'iva_valor');

            return;
        }

        $payload = [
            'nome' => $dados['nome'],
            'codigo_barras' => ProductValidation::normalizarCodigoBarras($dados['codigo_barras'] ?? null),
            'categoria' => $dados['categoria'] ?: null,
            'preco_compra' => $dados['preco_compra'],
            'preco_venda' => $dados['preco_venda'],
            'iva_tipo' => $ivaTipo,
            'iva_valor' => $ivaValor,
            'iva_percentual' => $ivaPercentual,
            'is_active' => $dados['is_active'],
        ];

        if ($this->editingId) {
            Product::query()->where('id', $this->editingId)->update($payload);
        } else {
            Product::query()->create([
                ...$payload,
                'stock' => 0,
            ]);
        }

        session()->flash('toast', [
            'type' => 'success',
            'message' => $this->editingId
                ? __('toasts.product_updated')
                : __('toasts.product_created_reload'),
        ]);
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->resetForm();
    }

    private function regrasValidacao(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'codigo_barras' => ProductValidation::regrasCodigoBarras($this->editingId),
            'categoria' => ['nullable', 'string', 'max:255'],
            'preco_compra' => ['required', 'numeric'],
            'preco_venda' => ['required', 'numeric'],
            'iva_tipo' => ['required', 'in:ISENTO,PERCENTUAL,MONETARIO'],
            'iva_percentual' => ['required_if:iva_tipo,PERCENTUAL', 'nullable', 'numeric', 'gte:0'],
            'iva_valor' => ['required_if:iva_tipo,MONETARIO', 'nullable', 'numeric', 'gte:0'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function atributosValidacao(): array
    {
        return [
            'nome' => __('app.fields.name'),
            'codigo_barras' => __('app.fields.barcode'),
            'categoria' => __('app.fields.category'),
            'preco_compra' => __('app.fields.purchase_price'),
            'preco_venda' => __('app.fields.sale_price'),
            'iva_percentual' => __('app.fields.iva_percent'),
            'iva_valor' => __('app.fields.iva_amount'),
        ];
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
        $this->stockAtual = '0';
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('products.view'), 403);

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
            ->layout('components.layouts.desktop', ['title' => __('pages.titles.products')])
            ->with(['produtos' => $produtos]);
    }
}
