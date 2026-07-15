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

        $context = $this->relatorioDoPedido($request, $builder);
        $registers = Register::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);

        $pdfUrl = route('operator-reports.pdf', array_filter([
            'periodo_inicio' => $context['inicio']->toDateString(),
            'periodo_fim' => $context['fim']->toDateString(),
            'register_id' => $context['registerId'],
        ]));

        return view('admin.operator-reports.index', [
            'relatorio' => $context['relatorio'],
            'registers' => $registers,
            'periodo_inicio' => $context['periodo_inicio'],
            'periodo_fim' => $context['periodo_fim'],
            'registerFilter' => $context['registerFilter'],
            'pdfUrl' => $pdfUrl,
        ]);
    }

    public function detail(Request $request, OperatorSalesReportBuilder $builder)
    {
        $this->authorizeAdmin('operator_reports.view');

        $operadorChave = $request->string('operador')->toString();
        abort_if($operadorChave === '', 404);

        $context = $this->relatorioDoPedido($request, $builder);
        $operador = collect($context['relatorio']['operadores'])->firstWhere('chave', $operadorChave);
        abort_if($operador === null, 404);

        return view('admin.operator-reports.detail', [
            'operador' => $operador,
            'backUrl' => route('operator-reports.index', $request->only([
                'periodo_inicio',
                'periodo_fim',
                'registerFilter',
            ])),
        ]);
    }

    /** @return array{relatorio: array<string, mixed>, periodo_inicio: string, periodo_fim: string, registerFilter: string, registerId: ?string, inicio: Carbon, fim: Carbon} */
    private function relatorioDoPedido(Request $request, OperatorSalesReportBuilder $builder): array
    {
        $periodo_inicio = $request->string('periodo_inicio')->toString() ?: now()->startOfMonth()->toDateString();
        $periodo_fim = $request->string('periodo_fim')->toString() ?: now()->toDateString();
        $registerFilter = $request->string('registerFilter')->toString();

        $registerId = $registerFilter !== '' ? $registerFilter : null;
        if ($registerId && ! $this->uuidValido($registerId)) {
            $registerId = null;
        }

        [$inicio, $fim] = $this->resolverIntervaloDatas($periodo_inicio, $periodo_fim);

        $relatorio = $this->intervaloDatasValido($periodo_inicio, $periodo_fim)
            ? $builder->build($inicio, $fim, $registerId)
            : $this->relatorioVazio($inicio, $fim, $registerId);

        return [
            'relatorio' => $relatorio,
            'periodo_inicio' => $periodo_inicio,
            'periodo_fim' => $periodo_fim,
            'registerFilter' => $registerFilter,
            'registerId' => $registerId,
            'inicio' => $inicio,
            'fim' => $fim,
        ];
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
