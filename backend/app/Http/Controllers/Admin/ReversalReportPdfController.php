<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\Register;
use App\Services\ReversalReportBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReversalReportPdfController extends Controller
{
    public function __invoke(Request $request, ReversalReportBuilder $builder): Response
    {
        abort_unless(auth()->user()?->can('reversals.view'), 403);

        $dados = $request->validate([
            'periodo_inicio' => ['required', 'date'],
            'periodo_fim' => ['required', 'date', 'after_or_equal:periodo_inicio'],
            'status' => ['nullable', 'in:PENDING,APPROVED,REJECTED'],
            'register_id' => ['nullable', 'uuid', 'exists:registers,id'],
        ]);

        $relatorio = $builder->build(
            Carbon::parse($dados['periodo_inicio']),
            Carbon::parse($dados['periodo_fim']),
            $dados['status'] ?? null,
            $dados['register_id'] ?? null,
        );

        $empresa = CompanyProfile::query()->first();
        $caixa = isset($dados['register_id'])
            ? Register::query()->find($dados['register_id'])
            : null;

        $pdf = Pdf::loadView('pdf.reversals-report', [
            'relatorio' => $relatorio,
            'empresa' => $empresa,
            'caixa' => $caixa,
        ])->setPaper('a4', 'portrait');

        $nome = 'relatorio-reversoes-'
            .Carbon::parse($dados['periodo_inicio'])->format('Ymd')
            .'-'
            .Carbon::parse($dados['periodo_fim'])->format('Ymd')
            .'.pdf';

        return $pdf->download($nome);
    }
}
