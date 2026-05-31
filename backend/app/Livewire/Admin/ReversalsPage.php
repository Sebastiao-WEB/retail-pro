<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ValidatesDateInput;
use App\Models\Register;
use App\Models\SaleReversalRequest;
use App\Services\ReversalReportBuilder;
use App\Services\SaleReversalService;
use Livewire\Component;
use Livewire\WithPagination;

class ReversalsPage extends Component
{
    use ValidatesDateInput;
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $periodo_inicio = '';

    public string $periodo_fim = '';

    public string $registerFilter = '';

    public bool $decisionModalOpen = false;

    public ?string $decisionId = null;

    public string $decisionStatus = 'APPROVED';

    public string $decisionReason = '';

    public function mount(): void
    {
        $this->periodo_inicio = now()->startOfMonth()->toDateString();
        $this->periodo_fim = now()->toDateString();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedRegisterFilter(): void
    {
        $this->resetPage();
    }

    public function aplicarPeriodoMes(): void
    {
        $this->periodo_inicio = now()->startOfMonth()->toDateString();
        $this->periodo_fim = now()->toDateString();
    }

    public function aplicarPeriodoMesAnterior(): void
    {
        $inicio = now()->subMonth()->startOfMonth();
        $this->periodo_inicio = $inicio->toDateString();
        $this->periodo_fim = $inicio->copy()->endOfMonth()->toDateString();
    }

    public function pdfUrl(): string
    {
        [$inicio, $fim] = $this->resolverIntervaloDatas($this->periodo_inicio, $this->periodo_fim);

        return route('reversals.pdf', array_filter([
            'periodo_inicio' => $inicio->toDateString(),
            'periodo_fim' => $fim->toDateString(),
            'status' => $this->statusFilter ?: null,
            'register_id' => $this->registerFilter ?: null,
        ]));
    }

    public function openDecisionModal(string $id, string $status): void
    {
        abort_unless(auth()->user()?->can('reversals.manage'), 403);
        $this->decisionId = $id;
        $this->decisionStatus = $status;
        $this->decisionReason = '';
        $this->decisionModalOpen = true;
    }

    public function applyDecision(SaleReversalService $reversalService): void
    {
        abort_unless(auth()->user()?->can('reversals.manage'), 403);
        $dados = $this->validate([
            'decisionReason' => ['nullable', 'string', 'max:500'],
        ]);

        if (! $this->decisionId) {
            return;
        }

        $pedido = SaleReversalRequest::query()->with('sale')->find($this->decisionId);
        if (! $pedido) {
            return;
        }

        try {
            if ($this->decisionStatus === 'APPROVED') {
                $reversalService->approve($pedido, $dados['decisionReason'] ?? null, auth()->id());
                session()->flash('toast', ['type' => 'success', 'message' => 'Reversão aprovada e stock estornado.']);
            } else {
                $reversalService->reject($pedido, $dados['decisionReason'] ?? null, auth()->id());
                session()->flash('toast', ['type' => 'success', 'message' => 'Solicitação rejeitada.']);
            }
        } catch (\Throwable $e) {
            session()->flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        $this->decisionModalOpen = false;
        $this->decisionId = null;
    }

    public function render(ReversalReportBuilder $builder)
    {
        abort_unless(auth()->user()?->can('reversals.view'), 403);

        $registerId = $this->registerFilter !== '' ? $this->registerFilter : null;
        if ($registerId && ! $this->uuidValido($registerId)) {
            $registerId = null;
        }

        $reversoes = SaleReversalRequest::query()
            ->with(['sale.register'])
            ->when($this->search !== '', function ($q) {
                $q->where(function ($inner) {
                    $inner->where('sale_id', 'like', "%{$this->search}%")
                        ->orWhere('reason', 'like', "%{$this->search}%")
                        ->orWhereHas('sale', fn ($sale) => $sale
                            ->where('referencia', 'like', "%{$this->search}%")
                            ->orWhere('operador', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($registerId, fn ($q) => $q->whereHas('sale', fn ($sale) => $sale->where('register_id', $registerId)))
            ->latest('requested_at')
            ->paginate(10);

        [$inicio, $fim] = $this->resolverIntervaloDatas($this->periodo_inicio, $this->periodo_fim);

        $totais = $this->intervaloDatasValido($this->periodo_inicio, $this->periodo_fim)
            ? $builder->totais(
                $inicio,
                $fim,
                $this->statusFilter !== '' ? $this->statusFilter : null,
                $registerId,
            )
            : [
                'total' => 0,
                'pendentes' => 0,
                'aprovadas' => 0,
                'rejeitadas' => 0,
                'valor_revertido' => 0.0,
            ];

        $registers = Register::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);

        return view('livewire.admin.reversals-page')
            ->layout('components.layouts.desktop', ['title' => 'Reversões | RetailPro'])
            ->with([
                'reversoes' => $reversoes,
                'totais' => $totais,
                'registers' => $registers,
            ]);
    }
}
