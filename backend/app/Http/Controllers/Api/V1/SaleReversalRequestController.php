<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesApiClient;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReversalRequest;
use App\Services\SaleReversalService;
use App\Support\SaleItemTaxSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaleReversalRequestController extends Controller
{
    use ResolvesApiClient;

    public function __construct(private readonly SaleReversalService $reversalService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(20, max(1, (int) $request->query('per_page', 10)));

        $query = SaleReversalRequest::query()
            ->with(['sale.itens.product', 'requestedByUser', 'approvedByUser']);

        // Operador de caixa: só as reversões que ele solicitou.
        if ($this->isPosApiClient()) {
            $userId = auth('api')->id();
            if ($userId) {
                $query->where('requested_by', $userId);
            }
        }

        $paginado = $query
            ->latest('requested_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => [
                'items' => collect($paginado->items())
                    ->map(fn (SaleReversalRequest $item) => $this->serializarPedido($item))
                    ->values(),
                'meta' => [
                    'current_page' => $paginado->currentPage(),
                    'last_page' => $paginado->lastPage(),
                    'per_page' => $paginado->perPage(),
                    'total' => $paginado->total(),
                ],
            ],
        ]);
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

    /** @return array<string, mixed> */
    private function serializarPedido(SaleReversalRequest $item): array
    {
        $item->loadMissing(['sale.itens.product', 'requestedByUser', 'approvedByUser']);

        return [
            'id' => $item->id,
            'saleId' => $item->sale_id,
            'requestedBy' => $item->requestedByUser?->name ?? '—',
            'approvedBy' => $item->approvedByUser?->name,
            'status' => $item->status,
            'reason' => $item->reason,
            'requestedAt' => optional($item->requested_at)->toISOString(),
            'decidedAt' => optional($item->decided_at)->toISOString(),
            'sale' => $item->sale ? $this->serializarVenda($item->sale) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function serializarVenda(Sale $sale): array
    {
        $sale->loadMissing(['itens.product']);

        return [
            'id' => $sale->id,
            'referencia' => $sale->referencia,
            'cliente' => $sale->cliente,
            'caixa' => $sale->caixa,
            'operador' => $sale->operador,
            'metodoPagamento' => $sale->metodo_pagamento,
            'estado' => $sale->estado,
            'subtotal' => (float) $sale->subtotal,
            'descontoAplicado' => (float) $sale->desconto_aplicado,
            'total' => (float) $sale->total,
            'valorPago' => (float) $sale->valor_pago,
            'troco' => (float) $sale->troco,
            'data' => optional($sale->data)->toISOString(),
            'createdAt' => optional($sale->created_at)->toISOString(),
            'itens' => $sale->itens
                ->map(fn (SaleItem $saleItem) => $this->serializarItemVenda($saleItem))
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializarItemVenda(SaleItem $item): array
    {
        $base = [
            'produtoId' => $item->produto_id,
            'nome' => $item->nome,
            'quantidade' => (float) $item->quantidade,
            'precoVenda' => (float) $item->preco_venda,
            'precoSemIva' => (float) $item->preco_sem_iva,
            'ivaPercentual' => (float) $item->iva_percentual,
            'valorIvaUnitario' => (float) $item->valor_iva_unitario,
            'subtotal' => (float) $item->subtotal,
        ];

        $iva = SaleItemTaxSnapshot::fromPayload($base, $item->product);

        return [
            'produtoId' => $base['produtoId'],
            'nome' => $base['nome'],
            'quantidade' => $base['quantidade'],
            'precoVenda' => $iva['precoVenda'],
            'precoSemIva' => $iva['precoSemIva'],
            'ivaPercentual' => $iva['ivaPercentual'],
            'valorIvaUnitario' => $iva['valorIvaUnitario'],
            'subtotal' => $base['subtotal'],
        ];
    }
}
