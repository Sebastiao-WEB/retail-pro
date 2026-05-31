<?php

namespace App\Livewire\Admin;

use App\Models\Register;
use App\Models\SaleReversalRequest;
use App\Services\ReversalReportBuilder;
use App\Services\SaleReversalService;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class ReversalsPage extends Component
{
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
        [$inicio, $fim] = $this->resolverPeriodo();

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

        [$inicio, $fim] = $this->resolverPeriodo();

        $totais = $this->periodoCompletoValido()
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

    /** @return array{0: Carbon, 1: Carbon} */
    private function resolverPeriodo(): array
    {
        $inicio = $this->dataValida($this->periodo_inicio)
            ? Carbon::parse($this->periodo_inicio)->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $fim = $this->dataValida($this->periodo_fim)
            ? Carbon::parse($this->periodo_fim)->endOfDay()
            : now()->endOfDay();

        if ($fim->lt($inicio)) {
            $fim = $inicio->copy()->endOfDay();
        }

        return [$inicio, $fim];
    }

    private function periodoCompletoValido(): bool
    {
        if (! $this->dataValida($this->periodo_inicio) || ! $this->dataValida($this->periodo_fim)) {
            return false;
        }

        return Carbon::parse($this->periodo_fim)->gte(Carbon::parse($this->periodo_inicio));
    }

    private function dataValida(?string $valor): bool
    {
        if ($valor === null || $valor === '') {
            return false;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return false;
        }

        [$ano, $mes, $dia] = array_map('intval', explode('-', $valor));

        return checkdate($mes, $dia, $ano);
    }

    private function uuidValido(?string $valor): bool
    {
        if ($valor === null || $valor === '') {
            return false;
        }

        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $valor);
    }
}
