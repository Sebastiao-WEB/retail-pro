<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ValidatesDateInput;
use App\Models\Register;
use App\Services\OperatorSalesReportBuilder;
use Carbon\Carbon;
use Livewire\Component;

class OperatorReportsPage extends Component
{
    use ValidatesDateInput;

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
        [$inicio, $fim] = $this->resolverIntervaloDatas($this->periodo_inicio, $this->periodo_fim);

        return route('operator-reports.pdf', array_filter([
            'periodo_inicio' => $inicio->toDateString(),
            'periodo_fim' => $fim->toDateString(),
            'register_id' => $this->registerFilter ?: null,
        ]));
    }

    public function render(OperatorSalesReportBuilder $builder)
    {
        abort_unless(auth()->user()?->can('operator_reports.view'), 403);

        $registerId = $this->registerFilter !== '' ? $this->registerFilter : null;
        if ($registerId && ! $this->uuidValido($registerId)) {
            $registerId = null;
        }

        [$inicio, $fim] = $this->resolverIntervaloDatas($this->periodo_inicio, $this->periodo_fim);

        $relatorio = $this->intervaloDatasValido($this->periodo_inicio, $this->periodo_fim)
            ? $builder->build($inicio, $fim, $registerId)
            : $this->relatorioVazio($inicio, $fim, $registerId);

        $operadorSelecionado = null;
        if ($this->operadorDetalhe) {
            $operadorSelecionado = collect($relatorio['operadores'])
                ->firstWhere('chave', $this->operadorDetalhe);
        }

        $registers = Register::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);

        return view('livewire.admin.operator-reports-page')
            ->layout('components.layouts.desktop', ['title' => __('pages.titles.operator_reports')])
            ->with([
                'relatorio' => $relatorio,
                'operadorSelecionado' => $operadorSelecionado,
                'registers' => $registers,
            ]);
    }

    /** @return array<string, mixed> */
    private function relatorioVazio(Carbon $inicio, Carbon $fim, ?string $registerId): array
    {
        return [
            'periodo_inicio' => $inicio,
            'periodo_fim' => $fim,
            'register_id' => $registerId,
            'totais' => [
                'vendas' => 0.0,
                'custo' => 0.0,
                'lucro' => 0.0,
                'num_vendas' => 0,
            ],
            'operadores' => [],
        ];
    }
}
