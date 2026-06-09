<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesAssignedRegister;
use App\Models\CashSession;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Services\DashboardSummaryService;
use App\Support\ProductStockDisplay;
use App\Support\SaleItemTaxSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    use ResolvesAssignedRegister;

    public function __construct(private readonly DashboardSummaryService $dashboardSummary) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $dados = $request->validate([
            'register_id' => ['nullable', 'uuid'],
            'cash_session_id' => ['nullable', 'uuid'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'period' => ['nullable', 'in:today,7d,30d,month'],
        ]);

        $registerId = $this->resolverRegisterIdConsulta($request, $dados['register_id'] ?? null);
        if ($registerId instanceof \Illuminate\Http\JsonResponse) {
            return $registerId;
        }

        $perPage = min(50, max(1, (int) ($dados['per_page'] ?? 10)));

        $query = Sale::query()
            ->with(['itens.product'])
            ->where('register_id', $registerId)
            ->latest('created_at')
            ->latest('data');

        if (! empty($dados['cash_session_id'])) {
            $query->where('cash_session_id', $dados['cash_session_id']);
        }

        if (! empty($dados['period'])) {
            [$inicio, $fim] = $this->dashboardSummary->periodRange($dados['period']);
            $query->whereBetween('data', [$inicio, $fim]);
        }

        $paginado = $query->paginate($perPage);

        return response()->json([
            'data' => collect($paginado->items())->map(fn (Sale $sale) => $this->serializarVenda($sale))->values(),
            'meta' => [
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'per_page' => $paginado->perPage(),
                'total' => $paginado->total(),
                'register_id' => $registerId,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'id' => ['nullable', 'uuid'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'cliente' => ['required', 'string', 'max:255'],
            'caixa' => ['nullable', 'string', 'max:255'],
            'operador' => ['nullable', 'string', 'max:255'],
            'register_id' => ['nullable', 'uuid'],
            'registerId' => ['nullable', 'uuid'],
            'source_location_id' => ['nullable', 'uuid'],
            'sourceLocationId' => ['nullable', 'uuid'],
            'cash_session_id' => ['nullable', 'uuid'],
            'cashSessionId' => ['nullable', 'uuid'],
            'metodoPagamento' => ['required', 'string', 'max:50'],
            'subtotal' => ['required', 'numeric'],
            'descontoAplicado' => ['nullable', 'numeric'],
            'total' => ['required', 'numeric'],
            'valorPago' => ['nullable', 'numeric'],
            'troco' => ['nullable', 'numeric'],
            'data' => ['nullable', 'date'],
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.produtoId' => ['nullable', 'uuid'],
            'itens.*.nome' => ['required', 'string', 'max:255'],
            'itens.*.quantidade' => ['required', 'numeric'],
            'itens.*.precoVenda' => ['required', 'numeric'],
            'itens.*.precoSemIva' => ['nullable', 'numeric'],
            'itens.*.ivaPercentual' => ['nullable', 'numeric'],
            'itens.*.valorIvaUnitario' => ['nullable', 'numeric'],
            'itens.*.subtotal' => ['required', 'numeric'],
            'stockVersions' => ['nullable', 'array'],
            'stockVersions.*' => ['nullable', 'string', 'max:64'],
        ]);

        $stockVersions = is_array($dados['stockVersions'] ?? null) ? $dados['stockVersions'] : [];

        $registerId = $this->resolverRegisterIdConsulta(
            $request,
            $dados['register_id'] ?? ($dados['registerId'] ?? null)
        );
        if ($registerId instanceof \Illuminate\Http\JsonResponse) {
            return $registerId;
        }

        $cashSessionId = $this->resolverCashSessionIdVenda(
            $registerId,
            $dados['cash_session_id'] ?? ($dados['cashSessionId'] ?? null)
        );

        $sale = DB::transaction(function () use ($dados, $stockVersions, $registerId, $cashSessionId) {
            $locationId = $dados['source_location_id'] ?? ($dados['sourceLocationId'] ?? null);
            $saleId = $dados['id'] ?? (string) Str::uuid();

            if ($saleId !== '') {
                $existente = Sale::query()->with('itens')->find($saleId);
                if ($existente) {
                    if (! $this->vendaCorrespondePayload($existente, $dados)) {
                        throw ValidationException::withMessages([
                            'id' => ['Já existe uma venda com este identificador, mas com conteúdo diferente.'],
                        ]);
                    }

                    return $existente;
                }
            }

            $itensComProduto = collect($dados['itens'])
                ->filter(fn (array $item) => ! empty($item['produtoId']))
                ->values();

            if ($itensComProduto->isNotEmpty() && ! $locationId) {
                throw ValidationException::withMessages([
                    'source_location_id' => ['Localização de stock obrigatória para baixar inventário.'],
                ]);
            }

            $saldosPorProduto = [];
            foreach ($itensComProduto as $index => $item) {
                $produtoId = $item['produtoId'];
                $quantidade = (float) $item['quantidade'];

                if ($quantidade <= 0) {
                    throw ValidationException::withMessages([
                        "itens.{$index}.quantidade" => ['Quantidade inválida.'],
                    ]);
                }

                if (! isset($saldosPorProduto[$produtoId])) {
                    $balanceLeitura = StockBalance::query()
                        ->where('location_id', $locationId)
                        ->where('product_id', $produtoId)
                        ->lockForUpdate()
                        ->first();

                    $versaoCliente = trim((string) ($stockVersions[$produtoId] ?? ''));
                    if ($versaoCliente !== '' && $balanceLeitura) {
                        $versaoServidor = (string) optional($balanceLeitura->updated_at)->toJSON();
                        if ($versaoServidor !== '' && $versaoCliente !== $versaoServidor) {
                            throw ValidationException::withMessages([
                                'itens' => ["O stock de \"{$item['nome']}\" foi actualizado noutro caixa. Actualize o ecrã e tente novamente."],
                            ]);
                        }
                    }

                    $saldosPorProduto[$produtoId] = [
                        'balance' => null,
                        'reservado' => 0.0,
                        'nome' => $item['nome'],
                    ];
                }

                $saldosPorProduto[$produtoId]['reservado'] += $quantidade;
            }

            foreach ($saldosPorProduto as $produtoId => $info) {
                $reservado = (float) $info['reservado'];
                $balance = ProductStockDisplay::resolverSaldoParaVenda($locationId, $produtoId, $reservado);
                $saldosPorProduto[$produtoId]['balance'] = $balance;

                if (! $balance || (float) $balance->quantity < $reservado) {
                    throw ValidationException::withMessages([
                        'itens' => ["Stock insuficiente para \"{$info['nome']}\" na localização de venda."],
                    ]);
                }
            }

            $sale = Sale::create([
                'id' => $saleId,
                'referencia' => $this->gerarReferenciaUnica($dados['referencia'] ?? null),
                'register_id' => $registerId,
                'source_location_id' => $locationId,
                'cash_session_id' => $cashSessionId,
                'user_id' => auth('api')->id(),
                'cliente' => $dados['cliente'],
                'caixa' => $dados['caixa'] ?? null,
                'operador' => $dados['operador'] ?? null,
                'metodo_pagamento' => $dados['metodoPagamento'],
                'estado' => 'Concluida',
                'subtotal' => $dados['subtotal'],
                'desconto_aplicado' => $dados['descontoAplicado'] ?? 0,
                'total' => $dados['total'],
                'valor_pago' => $dados['valorPago'] ?? 0,
                'troco' => $dados['troco'] ?? 0,
                'data' => $dados['data'] ?? now(),
            ]);

            $produtosAfetados = [];

            $produtosPorId = Product::query()
                ->whereIn('id', collect($dados['itens'])->pluck('produtoId')->filter()->unique()->values())
                ->get()
                ->keyBy('id');

            foreach ($dados['itens'] as $item) {
                $produto = ! empty($item['produtoId']) ? $produtosPorId->get($item['produtoId']) : null;
                $iva = SaleItemTaxSnapshot::fromPayload($item, $produto);

                SaleItem::create([
                    'id' => (string) Str::uuid(),
                    'sale_id' => $sale->id,
                    'produto_id' => $item['produtoId'] ?? null,
                    'nome' => $item['nome'],
                    'quantidade' => $item['quantidade'],
                    'preco_venda' => $iva['precoVenda'],
                    'preco_sem_iva' => $iva['precoSemIva'],
                    'iva_percentual' => $iva['ivaPercentual'],
                    'valor_iva_unitario' => $iva['valorIvaUnitario'],
                    'subtotal' => $item['subtotal'],
                ]);

                $produtoId = $item['produtoId'] ?? null;
                if (! $produtoId || ! $locationId) {
                    continue;
                }

                $quantidade = (float) $item['quantidade'];
                if ($quantidade <= 0) {
                    continue;
                }

                $balance = $saldosPorProduto[$produtoId]['balance'];
                $balance->quantity = (float) $balance->quantity - $quantidade;
                $balance->save();

                StockMovement::query()->create([
                    'id' => (string) Str::uuid(),
                    'product_id' => $produtoId,
                    'from_location_id' => $locationId,
                    'to_location_id' => null,
                    'type' => 'OUT',
                    'quantity' => $quantidade,
                    'unit_cost' => (float) ($item['precoSemIva'] ?? $item['precoVenda'] ?? 0),
                    'reference_type' => 'SALE',
                    'reference_id' => $sale->id,
                    'note' => 'Saída por venda '.$sale->referencia,
                    'performed_by' => auth('api')->id(),
                ]);

                $produtosAfetados[$produtoId] = true;
            }

            foreach (array_keys($produtosAfetados) as $produtoId) {
                $produto = Product::query()->find($produtoId);
                if (! $produto) {
                    continue;
                }

                ProductStockDisplay::sincronizarStockGlobal($produtoId);
            }

            return $sale;
        });

        $sale->refresh()->load('itens');

        return response()->json([
            'message' => 'Venda registada com sucesso.',
            'data' => array_merge($this->serializarVenda($sale), [
                'status' => 'COMPLETED',
            ]),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Sale $sale)
    {
        $sale->load(['itens.product']);

        return response()->json([
            'data' => $this->serializarVenda($sale),
        ]);
    }

    public function update(Request $request, string $id) {}
    public function destroy(string $id) {}

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
            'userId' => $sale->user_id,
            'registerId' => $sale->register_id,
            'cashSessionId' => $sale->cash_session_id,
            'sourceLocationId' => $sale->source_location_id,
            'itens' => $sale->itens->map(fn (SaleItem $item) => $this->serializarItemVenda($item))->values(),
        ];
    }

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
            'ivaTipo' => SaleItemTaxSnapshot::ivaTipoProduto($item->product),
            'ivaPercentual' => $iva['ivaPercentual'],
            'valorIvaUnitario' => $iva['valorIvaUnitario'],
            'subtotal' => $base['subtotal'],
        ];
    }

    private function resolverCashSessionIdVenda(string $registerId, ?string $informado): ?string
    {
        if ($informado) {
            $sessao = CashSession::query()->find($informado);
            if (
                $sessao
                && $sessao->register_id === $registerId
                && strtoupper((string) $sessao->status) === 'OPEN'
            ) {
                return $sessao->id;
            }
        }

        $userId = auth('api')->id();

        return CashSession::query()
            ->where('register_id', $registerId)
            ->where('status', 'OPEN')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->latest('opened_at')
            ->value('id');
    }

    private function vendaCorrespondePayload(Sale $sale, array $dados): bool
    {
        return $this->assinaturaItensPayload($dados['itens'] ?? [])
            === $this->assinaturaItensVenda($sale->itens)
            && abs((float) $sale->total - (float) ($dados['total'] ?? 0)) < 0.02;
    }

    private function assinaturaItensPayload(array $itens): string
    {
        return $this->hashAssinaturaItens(
            collect($itens)->map(fn (array $item) => [
                'produtoId' => (string) ($item['produtoId'] ?? ''),
                'quantidade' => round((float) ($item['quantidade'] ?? 0), 3),
                'subtotal' => round((float) ($item['subtotal'] ?? 0), 2),
            ])
        );
    }

    private function assinaturaItensVenda(Collection $itens): string
    {
        return $this->hashAssinaturaItens(
            $itens->map(fn (SaleItem $item) => [
                'produtoId' => (string) ($item->produto_id ?? ''),
                'quantidade' => round((float) $item->quantidade, 3),
                'subtotal' => round((float) $item->subtotal, 2),
            ])
        );
    }

    private function hashAssinaturaItens(Collection $linhas): string
    {
        $ordenadas = $linhas
            ->sortBy(fn (array $linha) => $linha['produtoId'].'|'.$linha['quantidade'])
            ->values()
            ->all();

        return hash('sha256', json_encode($ordenadas));
    }

    private function gerarReferenciaUnica(?string $preferida = null): string
    {
        $preferida = trim((string) $preferida);
        if ($preferida !== '' && ! Sale::query()->where('referencia', $preferida)->exists()) {
            return $preferida;
        }

        do {
            $referencia = sprintf(
                'VD-%s-%s',
                now()->format('Ymd-His'),
                strtoupper(Str::random(4))
            );
        } while (Sale::query()->where('referencia', $referencia)->exists());

        return $referencia;
    }
}
