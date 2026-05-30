<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SaleReversalRequest;
use App\Services\SaleReversalService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SaleReversalRequestController extends Controller
{
    public function __construct(private readonly SaleReversalService $reversalService) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $requests = SaleReversalRequest::query()
            ->with('sale')
            ->latest('requested_at')
            ->get()
            ->map(fn (SaleReversalRequest $item) => [
                'id' => $item->id,
                'saleId' => $item->sale_id,
                'requestedBy' => $item->requested_by,
                'approvedBy' => $item->approved_by,
                'status' => $item->status,
                'reason' => $item->reason,
                'requestedAt' => optional($item->requested_at)->toISOString(),
                'decidedAt' => optional($item->decided_at)->toISOString(),
            ]);

        return response()->json(['data' => $requests]);
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'sale_id' => ['nullable', 'uuid'],
            'venda_id' => ['nullable', 'uuid'],
            'reason' => ['nullable', 'string'],
        ]);

        $saleId = $dados['sale_id'] ?? ($dados['venda_id'] ?? null);
        if (! $saleId) {
            return response()->json([
                'message' => 'Identificador da venda é obrigatório.',
                'errors' => ['sale_id' => ['Informe sale_id ou venda_id.']],
            ], 422);
        }

        $sale = \App\Models\Sale::query()->find($saleId);
        if (! $sale) {
            return response()->json(['message' => 'Venda não encontrada.'], 404);
        }

        if (strcasecmp((string) $sale->estado, 'Revertida') === 0) {
            return response()->json(['message' => 'A venda já está revertida.'], 409);
        }

        $duplicada = SaleReversalRequest::query()
            ->where('sale_id', $sale->id)
            ->where('status', 'PENDING')
            ->exists();

        if ($duplicada) {
            return response()->json([
                'message' => 'Já existe solicitação de reversão pendente para esta venda.',
            ], 409);
        }

        $solicitacao = SaleReversalRequest::create([
            'id' => (string) Str::uuid(),
            'sale_id' => $sale->id,
            'requested_by' => auth('api')->id(),
            'status' => 'PENDING',
            'reason' => $dados['reason'] ?? null,
            'requested_at' => now(),
        ]);

        return response()->json([
            'message' => 'Solicitação de reversão criada.',
            'data' => [
                'id' => $solicitacao->id,
                'status' => $solicitacao->status,
            ],
        ], 201);
    }

    public function update(Request $request, SaleReversalRequest $saleReversalRequest)
    {
        $dados = $request->validate([
            'status' => ['required', 'in:PENDING,APPROVED,REJECTED'],
            'reason' => ['nullable', 'string'],
        ]);

        try {
            if ($dados['status'] === 'APPROVED') {
                $this->reversalService->approve(
                    $saleReversalRequest,
                    $dados['reason'] ?? null,
                    auth('api')->id()
                );
            } elseif ($dados['status'] === 'REJECTED') {
                $this->reversalService->reject(
                    $saleReversalRequest,
                    $dados['reason'] ?? null,
                    auth('api')->id()
                );
            } else {
                $saleReversalRequest->update([
                    'status' => $dados['status'],
                    'reason' => $dados['reason'] ?? $saleReversalRequest->reason,
                ]);
            }
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Solicitação de reversão atualizada.',
            'data' => [
                'id' => $saleReversalRequest->id,
                'status' => $saleReversalRequest->fresh()->status,
            ],
        ]);
    }
}
