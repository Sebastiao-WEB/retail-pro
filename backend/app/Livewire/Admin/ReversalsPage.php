<?php

namespace App\Livewire\Admin;

use App\Models\SaleReversalRequest;
use Livewire\Component;
use Livewire\WithPagination;

class ReversalsPage extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public bool $decisionModalOpen = false;
    public ?string $decisionId = null;
    public string $decisionStatus = 'APPROVED';
    public string $decisionReason = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openDecisionModal(string $id, string $status): void
    {
        abort_unless(auth()->user()?->can('reversals.manage'), 403);
        $this->decisionId = $id;
        $this->decisionStatus = $status;
        $this->decisionReason = '';
        $this->decisionModalOpen = true;
    }

    public function applyDecision(): void
    {
        abort_unless(auth()->user()?->can('reversals.manage'), 403);
        $dados = $this->validate([
            'decisionReason' => ['nullable', 'string', 'max:500'],
        ]);

        if (! $this->decisionId) {
            return;
        }

        $pedido = SaleReversalRequest::query()->find($this->decisionId);
        if (! $pedido) {
            return;
        }

        $pedido->update([
            'status' => $this->decisionStatus,
            'approved_by' => auth()->id(),
            'reason' => $dados['decisionReason'] !== '' ? $dados['decisionReason'] : $pedido->reason,
            'decided_at' => now(),
        ]);

        $this->decisionModalOpen = false;
        $this->decisionId = null;
        session()->flash('toast', ['type' => 'success', 'message' => 'Solicitação atualizada com sucesso.']);
    }

    public function render()
    {
        $reversoes = SaleReversalRequest::query()
            ->when($this->search !== '', function ($q) {
                $q->where(function ($inner) {
                    $inner->where('sale_id', 'like', "%{$this->search}%")
                        ->orWhere('reason', 'like', "%{$this->search}%");
                });
            })
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->latest('requested_at')
            ->paginate(10);

        return view('livewire.admin.reversals-page')
            ->layout('components.layouts.desktop', ['title' => 'Reversões | RetailPro'])
            ->with(['reversoes' => $reversoes]);
    }
}
