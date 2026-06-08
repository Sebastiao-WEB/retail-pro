<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Web\Concerns\AuthorizesAdminWeb;
use App\Http\Controllers\Admin\Web\Concerns\ValidatesDateInput;
use App\Models\Register;
use App\Services\OperatorSalesReportBuilder;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OperatorReportWebController extends Controller
{
    use AuthorizesAdminWeb;
    use ValidatesDateInput;

    public function index(Request $request, OperatorSalesReportBuilder $builder)
    {
        $this->authorizeAdmin('operator_reports.view');

        $periodo_inicio = $request->string('periodo_inicio')->toString() ?: now()->startOfMonth()->toDateString();
        $periodo_fim = $request->string('periodo_fim')->toString() ?: now()->toDateString();
        $registerFilter = $request->string('registerFilter')->toString();
        $operadorDetalhe = $request->string('operador')->toString();

        $registerId = $registerFilter !== '' ? $registerFilter : null;
        if ($registerId && ! $this->uuidValido($registerId)) {
            $registerId = null;
        }

        [$inicio, $fim] = $this->resolverIntervaloDatas($periodo_inicio, $periodo_fim);

        $relatorio = $this->intervaloDatasValido($periodo_inicio, $periodo_fim)
            ? $builder->build($inicio, $fim, $registerId)
            : $this->relatorioVazio($inicio, $fim, $registerId);

        $operadorSelecionado = null;
        if ($operadorDetalhe !== '') {
            $operadorSelecionado = collect($relatorio['operadores'])
                ->firstWhere('chave', $operadorDetalhe);
        }

        $registers = Register::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);

        $pdfUrl = route('operator-reports.pdf', array_filter([
            'periodo_inicio' => $inicio->toDateString(),
            'periodo_fim' => $fim->toDateString(),
            'register_id' => $registerId,
        ]));

        return view('admin.operator-reports.index', [
            'relatorio' => $relatorio,
            'operadorSelecionado' => $operadorSelecionado,
            'registers' => $registers,
            'periodo_inicio' => $periodo_inicio,
            'periodo_fim' => $periodo_fim,
            'registerFilter' => $registerFilter,
            'operadorDetalhe' => $operadorDetalhe,
            'pdfUrl' => $pdfUrl,
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
