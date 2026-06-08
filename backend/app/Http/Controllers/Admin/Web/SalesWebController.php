<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Web\Concerns\AuthorizesAdminWeb;
use App\Http\Controllers\Admin\Web\Concerns\RespondsAsJson;
use App\Http\Controllers\Admin\Web\Concerns\ValidatesDateInput;
use App\Models\Register;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesWebController extends Controller
{
    use AuthorizesAdminWeb;
    use RespondsAsJson;
    use ValidatesDateInput;

    public function index(Request $request)
    {
        $this->authorizeAdmin('sales.view');

        $filters = $this->filtersFromRequest($request);
        $vendas = $this->vendasQuery($filters)->latest('data')->paginate(12)->withQueryString();
        $registers = Register::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);
        $totalFiltrado = (float) (clone $this->vendasQuery($filters))->sum('total');

        return view('admin.sales.index', [
            ...$filters,
            'vendas' => $vendas,
            'registers' => $registers,
            'totalFiltrado' => $totalFiltrado,
        ]);
    }

    public function detail(Request $request, Sale $sale)
    {
        $this->authorizeAdmin('sales.view');

        $detalhe = Sale::query()
            ->with(['itens.product', 'register', 'cashSession', 'user'])
            ->findOrFail($sale->id);

        return view('admin.sales.detail', [
            'sale' => $detalhe,
            'backUrl' => route('sales.index', $request->only([
                'search',
                'registerFilter',
                'estadoFilter',
                'pagamentoFilter',
                'dateFrom',
                'dateTo',
            ])),
            'isCash' => strcasecmp($detalhe->metodo_pagamento, 'Dinheiro') === 0,
            'totalIva' => (float) $detalhe->itens->sum(fn ($item) => $item->ivaTotalLinha()),
        ]);
    }

    public function show(Sale $sale)
    {
        $this->authorizeAdmin('sales.view');

        $detalhe = Sale::query()
            ->with(['itens.product', 'register', 'cashSession', 'user'])
            ->findOrFail($sale->id);

        return $this->jsonOk($this->serializeSaleDetail($detalhe));
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $this->authorizeAdmin('sales.export');

        $filters = $this->filtersFromRequest($request);
        $filename = 'vendas-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, [
                'Referência',
                'Cliente',
                'Caixa',
                'Operador',
                'Pagamento',
                'Estado',
                'Subtotal',
                'Desconto',
                'Total',
                'Data',
            ], ';');

            $this->vendasQuery($filters)
                ->latest('data')
                ->chunk(200, function ($vendas) use ($handle) {
                    foreach ($vendas as $venda) {
                        fputcsv($handle, [
                            $venda->referencia,
                            $venda->cliente,
                            $venda->caixa ?? $venda->register?->name ?? '',
                            $venda->operador ?? '',
                            $venda->metodo_pagamento,
                            $venda->estado,
                            number_format((float) $venda->subtotal, 2, '.', ''),
                            number_format((float) $venda->desconto_aplicado, 2, '.', ''),
                            number_format((float) $venda->total, 2, '.', ''),
                            optional($venda->data)->format('Y-m-d H:i:s'),
                        ], ';');
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'search' => $request->string('search')->toString(),
            'registerFilter' => $request->string('registerFilter')->toString(),
            'estadoFilter' => $request->string('estadoFilter')->toString(),
            'pagamentoFilter' => $request->string('pagamentoFilter')->toString(),
            'dateFrom' => $request->string('dateFrom')->toString(),
            'dateTo' => $request->string('dateTo')->toString(),
        ];
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function vendasQuery(array $filters): Builder
    {
        return Sale::query()
            ->with('register')
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $q->where(function ($inner) use ($filters) {
                    $inner->where('referencia', 'like', "%{$filters['search']}%")
                        ->orWhere('cliente', 'like', "%{$filters['search']}%")
                        ->orWhere('metodo_pagamento', 'like', "%{$filters['search']}%")
                        ->orWhere('operador', 'like', "%{$filters['search']}%")
                        ->orWhere('caixa', 'like', "%{$filters['search']}%");
                });
            })
            ->when($filters['registerFilter'] !== '', fn ($q) => $q->where('register_id', $filters['registerFilter']))
            ->when($filters['estadoFilter'] !== '', fn ($q) => $q->where('estado', $filters['estadoFilter']))
            ->when($filters['pagamentoFilter'] !== '', fn ($q) => $q->where('metodo_pagamento', $filters['pagamentoFilter']))
            ->when($this->dataValida($filters['dateFrom']), fn ($q) => $q->whereDate('data', '>=', $filters['dateFrom']))
            ->when($this->dataValida($filters['dateTo']), fn ($q) => $q->whereDate('data', '<=', $filters['dateTo']));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSaleDetail(Sale $detalhe): array
    {
        return [
            'id' => $detalhe->id,
            'referencia' => $detalhe->referencia,
            'cliente' => $detalhe->cliente,
            'operador' => $detalhe->operador ?? $detalhe->user?->name ?? '—',
            'caixa' => $detalhe->caixa ?? $detalhe->register?->name ?? '—',
            'metodo_pagamento' => $detalhe->metodo_pagamento,
            'estado' => $detalhe->estado,
            'data' => optional($detalhe->data)->format('d/m/Y H:i'),
            'subtotal' => number_format((float) $detalhe->subtotal, 2, ',', '.'),
            'desconto_aplicado' => number_format((float) $detalhe->desconto_aplicado, 2, ',', '.'),
            'total' => number_format((float) $detalhe->total, 2, ',', '.'),
            'valor_pago' => number_format((float) $detalhe->valor_pago, 2, ',', '.'),
            'troco' => number_format((float) $detalhe->troco, 2, ',', '.'),
            'total_iva' => number_format((float) $detalhe->itens->sum(fn ($item) => $item->ivaTotalLinha()), 2, ',', '.'),
            'is_cash' => strcasecmp($detalhe->metodo_pagamento, 'Dinheiro') === 0,
            'itens' => $detalhe->itens->map(function ($item) {
                $tax = $item->resolvedTax();

                return [
                    'nome' => $item->nome,
                    'quantidade' => number_format((float) $item->quantidade, 2, ',', '.'),
                    'preco_venda' => number_format((float) $item->preco_venda, 2, ',', '.'),
                    'iva_percentual' => number_format((float) $tax['ivaPercentual'], 2, ',', '.'),
                    'iva_total' => number_format($item->ivaTotalLinha(), 2, ',', '.'),
                    'subtotal' => number_format((float) $item->subtotal, 2, ',', '.'),
                ];
            })->values()->all(),
        ];
    }
}
