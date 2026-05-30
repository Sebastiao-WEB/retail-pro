<?php

namespace App\Livewire\Admin;

use App\Models\BalanceSheet;
use App\Models\BalanceSheetLine;
use App\Services\BalanceSheetBuilder;
use Illuminate\Support\Str;
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

    /** @var array<string, string> */
    public array $lineValues = [];

    public string $novaSecao = 'ACTIVO';

    public string $novaRubrika = '';

    public string $novaGrupo = 'CIRCULANTE';

    public string $novaValor = '0';

    public function mount(): void
    {
        $this->data_referencia = now()->toDateString();
        $this->periodo_inicio = now()->startOfYear()->toDateString();
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
            'periodo_inicio' => ['nullable', 'date'],
            'periodo_fim' => ['nullable', 'date', 'after_or_equal:periodo_inicio'],
            'notas' => ['nullable', 'string'],
        ]);

        $balance = $builder->create($dados, auth()->id());
        $this->createModalOpen = false;
        $this->openEditModal($balance->id);
        session()->flash('toast', ['type' => 'success', 'message' => 'Balanço criado com rubricas automáticas.']);
    }

    public function openEditModal(string $id): void
    {
        abort_unless(auth()->user()?->can('balance_sheets.view'), 403);

        $balance = BalanceSheet::query()->with('lines')->findOrFail($id);
        $this->editingId = $balance->id;
        $this->titulo = $balance->titulo;
        $this->data_referencia = $balance->data_referencia->toDateString();
        $this->periodo_inicio = optional($balance->periodo_inicio)->toDateString() ?? '';
        $this->periodo_fim = optional($balance->periodo_fim)->toDateString() ?? '';
        $this->notas = (string) ($balance->notas ?? '');
        $this->lineValues = $balance->lines->mapWithKeys(fn (BalanceSheetLine $line) => [
            $line->id => number_format((float) $line->valor, 2, '.', ''),
        ])->all();
        $this->editModalOpen = true;
    }

    public function closeEditModal(): void
    {
        $this->editModalOpen = false;
        $this->editingId = null;
        $this->lineValues = [];
    }

    public function recalcularAutomaticos(BalanceSheetBuilder $builder): void
    {
        abort_unless(auth()->user()?->can('balance_sheets.manage'), 403);

        $balance = $this->obterBalanceEmEdicao();
        if ($balance->isFinalized()) {
            session()->flash('toast', ['type' => 'error', 'message' => 'Balanço finalizado não pode ser alterado.']);

            return;
        }

        $builder->syncAutomaticLines($balance);
        $balance->recalculateTotals();
        $this->openEditModal($balance->id);
        session()->flash('toast', ['type' => 'success', 'message' => 'Rubricas automáticas recalculadas.']);
    }

    public function guardarLinhas(): void
    {
        abort_unless(auth()->user()?->can('balance_sheets.manage'), 403);

        $balance = $this->obterBalanceEmEdicao();
        if ($balance->isFinalized()) {
            return;
        }

        foreach ($this->lineValues as $lineId => $valor) {
            $line = $balance->lines->firstWhere('id', $lineId);
            if (! $line || ($line->automatico && ! auth()->user()?->can('balance_sheets.manage'))) {
                continue;
            }
            $line->valor = max(0, (float) str_replace(',', '.', (string) $valor));
            $line->save();
        }

        $balance->fill([
            'titulo' => $this->titulo,
            'notas' => $this->notas ?: null,
        ])->save();

        $balance->recalculateTotals();
        $this->openEditModal($balance->id);
        session()->flash('toast', ['type' => 'success', 'message' => 'Balanço actualizado.']);
    }

    public function adicionarLinhaManual(): void
    {
        abort_unless(auth()->user()?->can('balance_sheets.manage'), 403);

        $balance = $this->obterBalanceEmEdicao();
        if ($balance->isFinalized()) {
            return;
        }

        $dados = $this->validate([
            'novaSecao' => ['required', 'in:ACTIVO,PASSIVO,CAPITAL'],
            'novaRubrika' => ['required', 'string', 'max:255'],
            'novaGrupo' => ['nullable', 'string', 'max:50'],
            'novaValor' => ['nullable', 'numeric', 'min:0'],
        ]);

        $ordemBase = (int) $balance->lines()->where('secao', $dados['novaSecao'])->max('ordem');

        BalanceSheetLine::query()->create([
            'id' => (string) Str::uuid(),
            'balance_sheet_id' => $balance->id,
            'secao' => $dados['novaSecao'],
            'grupo' => $dados['novaGrupo'] ?: null,
            'rubrika' => $dados['novaRubrika'],
            'valor' => (float) ($dados['novaValor'] ?? 0),
            'automatico' => false,
            'ordem' => $ordemBase + 1,
        ]);

        $balance->recalculateTotals();
        $this->novaRubrika = '';
        $this->novaValor = '0';
        $this->openEditModal($balance->id);
        session()->flash('toast', ['type' => 'success', 'message' => 'Rubrica manual adicionada.']);
    }

    public function removerLinha(string $lineId): void
    {
        abort_unless(auth()->user()?->can('balance_sheets.manage'), 403);

        $balance = $this->obterBalanceEmEdicao();
        if ($balance->isFinalized()) {
            return;
        }

        $line = BalanceSheetLine::query()
            ->where('balance_sheet_id', $balance->id)
            ->where('id', $lineId)
            ->where('automatico', false)
            ->first();

        if ($line) {
            $line->delete();
            $balance->recalculateTotals();
            $this->openEditModal($balance->id);
            session()->flash('toast', ['type' => 'success', 'message' => 'Rubrica removida.']);
        }
    }

    public function finalizar(): void
    {
        abort_unless(auth()->user()?->can('balance_sheets.manage'), 403);

        $balance = $this->obterBalanceEmEdicao();
        if ($balance->isFinalized()) {
            return;
        }

        $this->guardarLinhas();

        $balance->refresh();
        $balance->recalculateTotals();

        if (! $balance->isBalanced()) {
            session()->flash('toast', [
                'type' => 'warning',
                'message' => 'Balanço desbalanceado. Ajuste passivo/capital antes de finalizar ou finalize mesmo assim.',
            ]);
        }

        $balance->update([
            'status' => 'FINALIZED',
            'finalized_at' => now(),
        ]);

        $this->closeEditModal();
        session()->flash('toast', ['type' => 'success', 'message' => 'Balanço finalizado. Pode gerar o PDF.']);
    }

    public function render()
    {
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
            $balanceEmEdicao = BalanceSheet::query()->with('lines')->find($this->editingId);
        }

        return view('livewire.admin.balance-sheets-page')
            ->layout('components.layouts.desktop', ['title' => 'Balanço Patrimonial | RetailPro'])
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
        $this->titulo = 'Balanço Patrimonial '.now()->format('d/m/Y');
        $this->data_referencia = now()->toDateString();
        $this->periodo_inicio = now()->startOfYear()->toDateString();
        $this->periodo_fim = now()->toDateString();
        $this->notas = '';
    }
}
