<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Web\Concerns\AuthorizesAdminWeb;
use App\Http\Controllers\Admin\Web\Concerns\RespondsAsJson;
use App\Models\BalanceSheet;
use App\Services\BalanceSheetBuilder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BalanceSheetWebController extends Controller
{
    use AuthorizesAdminWeb;
    use RespondsAsJson;

    public function index(Request $request)
    {
        $this->authorizeAdmin('balance_sheets.view');

        $search = $request->string('search')->toString();

        $balances = BalanceSheet::query()
            ->with('preparedBy')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('referencia', 'like', "%{$search}%")
                        ->orWhere('titulo', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('periodo_fim')
            ->orderByDesc('data_referencia')
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.balance-sheets.index', [
            'balances' => $balances,
            'search' => $search,
            'canManage' => auth()->user()?->can('balance_sheets.manage') ?? false,
            'defaultForm' => $this->defaultCreateForm(),
        ]);
    }

    public function show(BalanceSheet $balanceSheet)
    {
        $this->authorizeAdmin('balance_sheets.view');

        $balance = BalanceSheet::query()
            ->with(['lines.product', 'locationLines'])
            ->findOrFail($balanceSheet->id);

        return $this->jsonOk($this->serializeBalanceSheet($balance));
    }

    public function store(Request $request, BalanceSheetBuilder $builder)
    {
        $this->authorizeAdmin('balance_sheets.manage');

        try {
            $dados = $this->validatedCreatePayload($request);
            $balance = $builder->create($dados, auth()->id());
        } catch (ValidationException $exception) {
            return $this->jsonFromValidation($exception);
        }

        return $this->jsonOk(
            $this->serializeBalanceSheet($balance->fresh(['lines.product', 'locationLines'])),
            __('toasts.balance_calculated'),
            201
        );
    }

    public function update(Request $request, BalanceSheet $balanceSheet)
    {
        $this->authorizeAdmin('balance_sheets.manage');

        if ($balanceSheet->isFinalized()) {
            return response()->json(['message' => __('toasts.balance_locked')], 422);
        }

        try {
            $dados = $request->validate([
                'titulo' => ['required', 'string', 'max:255'],
                'notas' => ['nullable', 'string'],
            ]);
        } catch (ValidationException $exception) {
            return $this->jsonFromValidation($exception);
        }

        $balanceSheet->update([
            'titulo' => $dados['titulo'],
            'notas' => $dados['notas'] ?: null,
        ]);

        return $this->jsonOk(
            $this->serializeBalanceSheet($balanceSheet->fresh(['lines.product', 'locationLines'])),
            __('toasts.balance_updated')
        );
    }

    public function recalculate(BalanceSheet $balanceSheet, BalanceSheetBuilder $builder)
    {
        $this->authorizeAdmin('balance_sheets.manage');

        if ($balanceSheet->isFinalized()) {
            return response()->json(['message' => __('toasts.balance_locked')], 422);
        }

        $builder->syncAutomaticLines($balanceSheet);

        return $this->jsonOk(
            $this->serializeBalanceSheet($balanceSheet->fresh(['lines.product', 'locationLines'])),
            __('toasts.balance_recalculated')
        );
    }

    public function finalize(Request $request, BalanceSheet $balanceSheet)
    {
        $this->authorizeAdmin('balance_sheets.manage');

        if ($balanceSheet->isFinalized()) {
            return response()->json(['message' => __('toasts.balance_locked')], 422);
        }

        try {
            $dados = $request->validate([
                'titulo' => ['required', 'string', 'max:255'],
                'notas' => ['nullable', 'string'],
            ]);
        } catch (ValidationException $exception) {
            return $this->jsonFromValidation($exception);
        }

        $balanceSheet->update([
            'titulo' => $dados['titulo'],
            'notas' => $dados['notas'] ?: null,
            'status' => 'FINALIZED',
            'finalized_at' => now(),
        ]);

        return $this->jsonOk(
            $this->serializeBalanceSheet($balanceSheet->fresh(['lines.product', 'locationLines'])),
            __('toasts.balance_finalized')
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultCreateForm(): array
    {
        return [
            'titulo' => 'Balanço de fecho '.now()->format('d/m/Y'),
            'data_referencia' => now()->toDateString(),
            'periodo_inicio' => now()->startOfMonth()->toDateString(),
            'periodo_fim' => now()->toDateString(),
            'notas' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedCreatePayload(Request $request): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'data_referencia' => ['required', 'date'],
            'periodo_inicio' => ['required', 'date'],
            'periodo_fim' => ['required', 'date', 'after_or_equal:periodo_inicio'],
            'notas' => ['nullable', 'string'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeBalanceSheet(BalanceSheet $balance): array
    {
        return [
            'id' => $balance->id,
            'referencia' => $balance->referencia,
            'titulo' => $balance->titulo,
            'notas' => (string) ($balance->notas ?? ''),
            'status' => $balance->status,
            'is_finalized' => $balance->isFinalized(),
            'data_referencia' => $balance->data_referencia->format('d/m/Y'),
            'periodo_inicio' => optional($balance->periodo_inicio)->format('d/m/Y'),
            'periodo_fim' => optional($balance->periodo_fim)->format('d/m/Y'),
            'pdf_url' => route('balance-sheets.pdf', $balance),
            'totals' => [
                'recargas_valor' => number_format((float) $balance->total_recargas_valor, 2, ',', '.'),
                'recargas_qtd' => number_format((float) $balance->total_recargas_qtd, 0, ',', '.'),
                'vendas_valor' => number_format((float) $balance->total_vendas_valor, 2, ',', '.'),
                'vendas_qtd' => number_format((float) $balance->total_vendas_qtd, 0, ',', '.'),
                'custo_vendas' => number_format((float) $balance->total_custo_vendas, 2, ',', '.'),
                'lucro' => number_format((float) $balance->total_lucro, 2, ',', '.'),
                'stock_valor_compra' => number_format((float) $balance->total_stock_valor_compra, 2, ',', '.'),
                'stock_qtd' => number_format((float) $balance->total_stock_qtd, 0, ',', '.'),
                'stock_valor_venda' => number_format((float) $balance->total_stock_valor_venda, 2, ',', '.'),
            ],
            'lines' => $balance->lines->map(fn ($linha) => [
                'rubrika' => $linha->rubrika,
                'codigo_barras' => $linha->product?->codigo_barras ?? '—',
                'qtd_recarregada' => number_format((float) $linha->qtd_recarregada, 0, ',', '.'),
                'valor_recarga' => number_format((float) $linha->valor_recarga, 2, ',', '.'),
                'qtd_vendida' => number_format((float) $linha->qtd_vendida, 0, ',', '.'),
                'valor_vendas' => number_format((float) $linha->valor_vendas, 2, ',', '.'),
                'custo_vendas' => number_format((float) $linha->custo_vendas, 2, ',', '.'),
                'lucro' => number_format((float) $linha->lucro, 2, ',', '.'),
                'qtd_stock' => number_format((float) $linha->qtd_stock, 0, ',', '.'),
                'valor_stock_compra' => number_format((float) $linha->valor_stock_compra, 2, ',', '.'),
                'valor_stock_venda' => number_format((float) $linha->valor_stock_venda, 2, ',', '.'),
            ])->values()->all(),
            'location_groups' => $balance->locationLines
                ->groupBy('location_id')
                ->map(function ($linhasLocal) {
                    $cabecalho = $linhasLocal->first();

                    return [
                        'local_codigo' => $cabecalho->local_codigo,
                        'local_nome' => $cabecalho->local_nome,
                        'total_qty' => number_format((float) $linhasLocal->sum('quantity'), 0, ',', '.'),
                        'total_cost' => number_format((float) $linhasLocal->sum('valor_compra'), 2, ',', '.'),
                        'lines' => $linhasLocal->map(fn ($linhaLocal) => [
                            'produto_nome' => $linhaLocal->produto_nome,
                            'codigo_barras' => $linhaLocal->codigo_barras ?? '—',
                            'quantity' => number_format((float) $linhaLocal->quantity, 0, ',', '.'),
                            'valor_compra' => number_format((float) $linhaLocal->valor_compra, 2, ',', '.'),
                            'valor_venda' => number_format((float) $linhaLocal->valor_venda, 2, ',', '.'),
                        ])->values()->all(),
                    ];
                })->values()->all(),
        ];
    }
}
