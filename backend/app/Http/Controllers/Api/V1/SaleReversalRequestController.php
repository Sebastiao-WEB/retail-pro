<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SaleReversalRequest;
use App\Models\Sale;
use App\Services\SaleReversalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $userId = auth('api')->id();

        $resultado = DB::transaction(function () use ($saleId, $dados, $userId) {
            $sale = Sale::query()->lockForUpdate()->find($saleId);
            if (! $sale) {
                return ['status' => 404, 'body' => ['message' => 'Venda não encontrada.']];
            }

            if (strcasecmp((string) $sale->estado, 'Revertida') === 0) {
                return ['status' => 409, 'body' => ['message' => 'A venda já está revertida.']];
            }

            $aprovada = SaleReversalRequest::query()
                ->where('sale_id', $sale->id)
                ->where('status', 'APPROVED')
                ->exists();

            if ($aprovada) {
                return ['status' => 409, 'body' => ['message' => 'A venda já foi revertida anteriormente.']];
            }

            $pendente = SaleReversalRequest::query()
                ->where('sale_id', $sale->id)
                ->where('status', 'PENDING')
                ->latest('requested_at')
                ->first();

            if ($pendente) {
                return [
                    'status' => 200,
                    'body' => [
                        'message' => 'Já existe solicitação de reversão pendente para esta venda.',
                        'data' => [
                            'id' => $pendente->id,
                            'status' => $pendente->status,
                            'reutilizada' => true,
                        ],
                    ],
                ];
            }

            $solicitacao = SaleReversalRequest::create([
                'id' => (string) Str::uuid(),
                'sale_id' => $sale->id,
                'requested_by' => $userId,
                'status' => 'PENDING',
                'reason' => $dados['reason'] ?? null,
                'requested_at' => now(),
            ]);

            return [
                'status' => 201,
                'body' => [
                    'message' => 'Solicitação de reversão criada.',
                    'data' => [
                        'id' => $solicitacao->id,
                        'status' => $solicitacao->status,
                    ],
                ],
            ];
        });

        return response()->json($resultado['body'], $resultado['status']);
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
