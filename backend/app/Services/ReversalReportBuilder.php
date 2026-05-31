<?php

namespace App\Services;

use App\Models\SaleReversalRequest;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReversalReportBuilder
{
    /** @return array{
     *     periodo_inicio: Carbon,
     *     periodo_fim: Carbon,
     *     tipo_intervalo: string,
     *     tipo_intervalo_label: string,
     *     register_id: ?string,
     *     status_filter: ?string,
     *     totais: array{
     *         total: int,
     *         pendentes: int,
     *         aprovadas: int,
     *         rejeitadas: int,
     *         valor_revertido: float
     *     },
     *     intervalos: list<array{
     *         label: string,
     *         inicio: Carbon,
     *         fim: Carbon,
     *         totais: array{total: int, pendentes: int, aprovadas: int, rejeitadas: int, valor_revertido: float},
     *         reversoes: list<array<string, mixed>>
     *     }>,
     *     reversoes: list<array<string, mixed>>
     * }
     */
    public function build(
        Carbon $inicio,
        Carbon $fim,
        ?string $statusFilter = null,
        ?string $registerId = null,
    ): array {
        $inicio = $inicio->copy()->startOfDay();
        $fim = $fim->copy()->endOfDay();

        $reversoes = $this->consultarReversoes($inicio, $fim, $statusFilter, $registerId);
        $linhas = $reversoes->map(fn (SaleReversalRequest $item) => $this->mapearReversao($item))->values()->all();

        [$tipoIntervalo, $intervalos] = $this->criarIntervalos($inicio, $fim);

        foreach ($linhas as $linha) {
            $data = Carbon::parse($linha['requested_at']);
            foreach ($intervalos as &$intervalo) {
                if ($data->betweenIncluded($intervalo['inicio'], $intervalo['fim'])) {
                    $intervalo['reversoes'][] = $linha;
                    $this->acumularTotais($intervalo['totais'], $linha);
                    break;
                }
            }
            unset($intervalo);
        }

        return [
            'periodo_inicio' => $inicio,
            'periodo_fim' => $fim,
            'tipo_intervalo' => $tipoIntervalo,
            'tipo_intervalo_label' => $this->labelTipoIntervalo($tipoIntervalo),
            'register_id' => $registerId,
            'status_filter' => $statusFilter !== '' ? $statusFilter : null,
            'totais' => $this->calcularTotais($linhas),
            'intervalos' => $intervalos,
            'reversoes' => $linhas,
        ];
    }

    /** @return array{total: int, pendentes: int, aprovadas: int, rejeitadas: int, valor_revertido: float} */
    public function totais(
        Carbon $inicio,
        Carbon $fim,
        ?string $statusFilter = null,
        ?string $registerId = null,
    ): array {
        $inicio = $inicio->copy()->startOfDay();
        $fim = $fim->copy()->endOfDay();

        $linhas = $this->consultarReversoes($inicio, $fim, $statusFilter, $registerId)
            ->map(fn (SaleReversalRequest $item) => $this->mapearReversao($item))
            ->values()
            ->all();

        return $this->calcularTotais($linhas);
    }

    /** @return Collection<int, SaleReversalRequest> */
    private function consultarReversoes(
        Carbon $inicio,
        Carbon $fim,
        ?string $statusFilter,
        ?string $registerId,
    ): Collection {
        return SaleReversalRequest::query()
            ->with(['sale.register', 'requestedByUser', 'approvedByUser'])
            ->whereBetween('requested_at', [$inicio, $fim])
            ->when($statusFilter !== null && $statusFilter !== '', fn ($q) => $q->where('status', $statusFilter))
            ->when($registerId, fn ($q) => $q->whereHas('sale', fn ($sale) => $sale->where('register_id', $registerId)))
            ->orderBy('requested_at')
            ->get();
    }

    /** @return array<string, mixed> */
    private function mapearReversao(SaleReversalRequest $item): array
    {
        $venda = $item->sale;

        return [
            'id' => $item->id,
            'sale_id' => $item->sale_id,
            'referencia' => $venda?->referencia ?? '—',
            'venda_total' => (float) ($venda?->total ?? 0),
            'operador' => $venda?->operador ?? '—',
            'cliente' => $venda?->cliente ?? '—',
            'caixa' => $venda?->caixa ?? $venda?->register?->name ?? '—',
            'venda_data' => $venda?->data,
            'status' => $item->status,
            'reason' => $item->reason,
            'requested_at' => $item->requested_at,
            'decided_at' => $item->decided_at,
            'requested_by' => $item->requestedByUser?->name ?? '—',
            'approved_by' => $item->approvedByUser?->name ?? '—',
        ];
    }

    /** @param list<array<string, mixed>> $linhas */
    /** @return array{total: int, pendentes: int, aprovadas: int, rejeitadas: int, valor_revertido: float} */
    private function calcularTotais(array $linhas): array
    {
        $totais = $this->totaisVazios();
        foreach ($linhas as $linha) {
            $this->acumularTotais($totais, $linha);
        }

        return $totais;
    }

    /** @return array{total: int, pendentes: int, aprovadas: int, rejeitadas: int, valor_revertido: float} */
    private function totaisVazios(): array
    {
        return [
            'total' => 0,
            'pendentes' => 0,
            'aprovadas' => 0,
            'rejeitadas' => 0,
            'valor_revertido' => 0.0,
        ];
    }

    /** @param array{total: int, pendentes: int, aprovadas: int, rejeitadas: int, valor_revertido: float} $totais */
    /** @param array<string, mixed> $linha */
    private function acumularTotais(array &$totais, array $linha): void
    {
        $totais['total']++;

        match ($linha['status']) {
            'PENDING' => $totais['pendentes']++,
            'APPROVED' => $totais['aprovadas']++,
            'REJECTED' => $totais['rejeitadas']++,
            default => null,
        };

        if ($linha['status'] === 'APPROVED') {
            $totais['valor_revertido'] += (float) $linha['venda_total'];
        }
    }

    /** @return array{0: string, 1: list<array{label: string, inicio: Carbon, fim: Carbon, totais: array, reversoes: list}>} */
    private function criarIntervalos(Carbon $inicio, Carbon $fim): array
    {
        $dias = $inicio->copy()->startOfDay()->diffInDays($fim->copy()->startOfDay()) + 1;
        $tipo = $dias <= 31 ? 'daily' : ($dias <= 92 ? 'weekly' : 'monthly');

        $intervalos = [];
        $cursor = $inicio->copy()->startOfDay();

        while ($cursor <= $fim) {
            $fimIntervalo = match ($tipo) {
                'daily' => $cursor->copy()->endOfDay(),
                'weekly' => $cursor->copy()->addDays(6)->endOfDay(),
                'monthly' => $cursor->copy()->endOfMonth()->endOfDay(),
            };

            if ($fimIntervalo > $fim) {
                $fimIntervalo = $fim->copy();
            }

            $intervalos[] = [
                'label' => $this->labelIntervalo($tipo, $cursor, $fimIntervalo),
                'inicio' => $cursor->copy(),
                'fim' => $fimIntervalo->copy(),
                'totais' => $this->totaisVazios(),
                'reversoes' => [],
            ];

            $cursor = match ($tipo) {
                'daily' => $fimIntervalo->copy()->addSecond()->startOfDay(),
                'weekly' => $fimIntervalo->copy()->addSecond()->startOfDay(),
                'monthly' => $fimIntervalo->copy()->addSecond()->startOfDay()->startOfMonth(),
            };
        }

        return [$tipo, $intervalos];
    }

    private function labelIntervalo(string $tipo, Carbon $inicio, Carbon $fim): string
    {
        return match ($tipo) {
            'daily' => $inicio->format('d/m/Y'),
            'weekly' => $inicio->format('d/m/Y').' — '.$fim->format('d/m/Y'),
            'monthly' => $inicio->translatedFormat('F Y'),
        };
    }

    private function labelTipoIntervalo(string $tipo): string
    {
        return match ($tipo) {
            'daily' => 'Diário',
            'weekly' => 'Semanal',
            'monthly' => 'Mensal',
        };
    }
}
