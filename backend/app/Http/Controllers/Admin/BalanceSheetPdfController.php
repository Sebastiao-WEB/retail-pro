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

        $balanceSheet->load(['lines.product', 'locationLines', 'preparedBy']);
        $empresa = CompanyProfile::query()->first();

        $pdf = Pdf::loadView('pdf.balance-sheet', [
            'balance' => $balanceSheet,
            'empresa' => $empresa,
        ])->setPaper('a4', 'landscape');

        $nome = 'balanco-fecho-'.$balanceSheet->referencia.'.pdf';

        return $pdf->download($nome);
    }
}
