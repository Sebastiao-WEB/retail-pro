<?php

namespace App\Livewire\Admin;

use App\Models\Register;
use App\Services\OperatorSalesReportBuilder;
use Carbon\Carbon;
use Livewire\Component;

class OperatorReportsPage extends Component
{
    public string $periodo_inicio = '';

    public string $periodo_fim = '';

    public string $registerFilter = '';

    public ?string $operadorDetalhe = null;

    public function mount(): void
    {
        $this->periodo_inicio = now()->startOfMonth()->toDateString();
        $this->periodo_fim = now()->toDateString();
    }

    public function aplicarPeriodoMes(): void
    {
        $this->periodo_inicio = now()->startOfMonth()->toDateString();
        $this->periodo_fim = now()->toDateString();
        $this->operadorDetalhe = null;
    }

    public function aplicarPeriodoMesAnterior(): void
    {
        $inicio = now()->subMonth()->startOfMonth();
        $this->periodo_inicio = $inicio->toDateString();
        $this->periodo_fim = $inicio->copy()->endOfMonth()->toDateString();
        $this->operadorDetalhe = null;
    }

    public function openOperadorDetalhe(string $chave): void
    {
        $this->operadorDetalhe = $chave;
    }

    public function closeOperadorDetalhe(): void
    {
        $this->operadorDetalhe = null;
    }

    public function pdfUrl(): string
    {
        return route('operator-reports.pdf', array_filter([
            'periodo_inicio' => $this->periodo_inicio,
            'periodo_fim' => $this->periodo_fim,
            'register_id' => $this->registerFilter ?: null,
        ]));
    }

    public function render(OperatorSalesReportBuilder $builder)
    {
        abort_unless(auth()->user()?->can('operator_reports.view'), 403);

        $this->validate([
            'periodo_inicio' => ['required', 'date'],
            'periodo_fim' => ['required', 'date', 'after_or_equal:periodo_inicio'],
        ]);

        $registerId = $this->registerFilter !== '' ? $this->registerFilter : null;
        if ($registerId) {
            $this->validate([
                'registerFilter' => ['uuid', 'exists:registers,id'],
            ]);
        }

        $relatorio = $builder->build(
            Carbon::parse($this->periodo_inicio),
            Carbon::parse($this->periodo_fim),
            $registerId,
        );

        $operadorSelecionado = null;
        if ($this->operadorDetalhe) {
            $operadorSelecionado = collect($relatorio['operadores'])
                ->firstWhere('chave', $this->operadorDetalhe);
        }

        $registers = Register::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);

        return view('livewire.admin.operator-reports-page')
            ->layout('components.layouts.desktop', ['title' => 'Relatório por Operador | RetailPro'])
            ->with([
                'relatorio' => $relatorio,
                'operadorSelecionado' => $operadorSelecionado,
                'registers' => $registers,
            ]);
    }
}
