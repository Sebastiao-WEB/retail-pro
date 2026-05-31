<?php

namespace App\Livewire\Admin;

use App\Models\CashSession;
use App\Models\Register;
use Livewire\Component;
use Livewire\WithPagination;

class CashSessionsClosedPage extends Component
{
    use WithPagination;

    public string $search = '';

    public string $registerFilter = '';

    public bool $detailModalOpen = false;

    public ?string $detailId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRegisterFilter(): void
    {
        $this->resetPage();
    }

    public function openDetail(string $id): void
    {
        $this->detailId = $id;
        $this->detailModalOpen = true;
    }

    public function closeDetail(): void
    {
        $this->detailModalOpen = false;
        $this->detailId = null;
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('cash_sessions.view'), 403);

        $fechos = CashSession::query()
            ->with(['register', 'user'])
            ->where('status', 'CLOSED')
            ->when($this->registerFilter !== '', fn ($q) => $q->where('register_id', $this->registerFilter))
            ->when($this->search !== '', function ($q) {
                $termo = $this->search;
                $q->where(function ($inner) use ($termo) {
                    $inner->where('note', 'like', "%{$termo}%")
                        ->orWhereHas('register', fn ($reg) => $reg->where('name', 'like', "%{$termo}%")->orWhere('code', 'like', "%{$termo}%"))
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$termo}%"));
                });
            })
            ->latest('closed_at')
            ->paginate(10);

        $detalhe = null;
        if ($this->detailId) {
            $detalhe = CashSession::query()
                ->with(['register', 'user'])
                ->where('status', 'CLOSED')
                ->find($this->detailId);
        }

        return view('livewire.admin.cash-sessions-closed-page')
            ->layout('components.layouts.desktop', ['title' => __('pages.titles.cash_sessions_closed')])
            ->with([
                'fechos' => $fechos,
                'detalhe' => $detalhe,
                'registers' => Register::query()->orderBy('name')->get(['id', 'code', 'name']),
            ]);
    }
}
