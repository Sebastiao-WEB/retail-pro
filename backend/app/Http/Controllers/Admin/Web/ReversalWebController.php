<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Web\Concerns\AuthorizesAdminWeb;
use App\Http\Controllers\Admin\Web\Concerns\RespondsAsJson;
use App\Http\Controllers\Admin\Web\Concerns\ValidatesDateInput;
use App\Models\Register;
use App\Models\SaleReversalRequest;
use App\Services\ReversalReportBuilder;
use App\Services\SaleReversalService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReversalWebController extends Controller
{
    use AuthorizesAdminWeb;
    use RespondsAsJson;
    use ValidatesDateInput;

    public function index(Request $request, ReversalReportBuilder $builder)
    {
        $this->authorizeAdmin('reversals.view');

        $search = $request->string('search')->toString();
        $statusFilter = $request->string('statusFilter')->toString();
        $periodo_inicio = $request->string('periodo_inicio')->toString() ?: now()->startOfMonth()->toDateString();
        $periodo_fim = $request->string('periodo_fim')->toString() ?: now()->toDateString();
        $registerFilter = $request->string('registerFilter')->toString();

        $registerId = $registerFilter !== '' ? $registerFilter : null;
        if ($registerId && ! $this->uuidValido($registerId)) {
            $registerId = null;
        }

        $reversoes = SaleReversalRequest::query()
            ->with(['sale.register'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('sale_id', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%")
                        ->orWhereHas('sale', fn ($sale) => $sale
                            ->where('referencia', 'like', "%{$search}%")
                            ->orWhere('operador', 'like', "%{$search}%"));
                });
            })
            ->when($statusFilter !== '', fn ($q) => $q->where('status', $statusFilter))
            ->when($registerId, fn ($q) => $q->whereHas('sale', fn ($sale) => $sale->where('register_id', $registerId)))
            ->latest('requested_at')
            ->paginate(10)
            ->withQueryString();

        [$inicio, $fim] = $this->resolverIntervaloDatas($periodo_inicio, $periodo_fim);

        $totais = $this->intervaloDatasValido($periodo_inicio, $periodo_fim)
            ? $builder->totais($inicio, $fim, $statusFilter !== '' ? $statusFilter : null, $registerId)
            : [
                'total' => 0,
                'pendentes' => 0,
                'aprovadas' => 0,
                'rejeitadas' => 0,
                'valor_revertido' => 0.0,
            ];

        $registers = Register::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);

        $pdfUrl = route('reversals.pdf', array_filter([
            'periodo_inicio' => $inicio->toDateString(),
            'periodo_fim' => $fim->toDateString(),
            'status' => $statusFilter ?: null,
            'register_id' => $registerId,
        ]));

        return view('admin.reversals.index', [
            'reversoes' => $reversoes,
            'totais' => $totais,
            'registers' => $registers,
            'search' => $search,
            'statusFilter' => $statusFilter,
            'periodo_inicio' => $periodo_inicio,
            'periodo_fim' => $periodo_fim,
            'registerFilter' => $registerFilter,
            'pdfUrl' => $pdfUrl,
            'canManage' => auth()->user()?->can('reversals.manage') ?? false,
        ]);
    }

    public function decide(Request $request, SaleReversalRequest $reversalRequest, SaleReversalService $reversalService)
    {
        $this->authorizeAdmin('reversals.manage');

        try {
            $dados = $request->validate([
                'decisionStatus' => ['required', 'in:APPROVED,REJECTED'],
                'decisionReason' => ['nullable', 'string', 'max:500'],
            ]);
        } catch (ValidationException $exception) {
            return $this->jsonFromValidation($exception);
        }

        $pedido = SaleReversalRequest::query()->with('sale')->findOrFail($reversalRequest->id);

        try {
            if ($dados['decisionStatus'] === 'APPROVED') {
                $reversalService->approve($pedido, $dados['decisionReason'] ?? null, auth()->id());
                $message = __('toasts.reversal_approved');
            } else {
                $reversalService->reject($pedido, $dados['decisionReason'] ?? null, auth()->id());
                $message = __('toasts.reversal_rejected');
            }
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->jsonOk(null, $message);
    }
}
