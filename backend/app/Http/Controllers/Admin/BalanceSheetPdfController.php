<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BalanceSheet;
use App\Models\CompanyProfile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class BalanceSheetPdfController extends Controller
{
    public function __invoke(BalanceSheet $balanceSheet): Response
    {
        abort_unless(auth()->user()?->can('balance_sheets.view'), 403);

        $balanceSheet->load(['lines', 'preparedBy']);
        $empresa = CompanyProfile::query()->first();

        $linhasPorSecao = [
            'ACTIVO' => $balanceSheet->lines->where('secao', 'ACTIVO')->values(),
            'PASSIVO' => $balanceSheet->lines->where('secao', 'PASSIVO')->values(),
            'CAPITAL' => $balanceSheet->lines->where('secao', 'CAPITAL')->values(),
        ];

        $pdf = Pdf::loadView('pdf.balance-sheet', [
            'balance' => $balanceSheet,
            'empresa' => $empresa,
            'linhasPorSecao' => $linhasPorSecao,
        ])->setPaper('a4', 'portrait');

        $nome = 'balanco-'.$balanceSheet->referencia.'.pdf';

        return $pdf->download($nome);
    }
}
