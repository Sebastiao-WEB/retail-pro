<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesAssignedRegister;
use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\TableOrder;
use App\Models\TableOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TableOrderController extends Controller
{
    use ResolvesAssignedRegister;

    public function index(Request $request)
    {
        $dados = $request->validate([
            'register_id' => ['nullable', 'uuid'],
            'registerId' => ['nullable', 'uuid'],
            'status' => ['nullable', 'in:OPEN,CLOSED'],
        ]);

        $registerId = $this->resolverRegisterIdConsulta(
            $request,
            $dados['register_id'] ?? ($dados['registerId'] ?? null)
        );
        if ($registerId instanceof \Illuminate\Http\JsonResponse) {
            return $registerId;
        }

        $status = strtoupper((string) ($dados['status'] ?? 'OPEN'));

        $pedidos = TableOrder::query()
            ->with(['itens', 'diningTable'])
            ->where('register_id', $registerId)
            ->where('status', $status)
            ->latest('opened_at')
            ->get();

        return response()->json([
            'data' => $pedidos->map(fn (TableOrder $pedido) => $this->serializarPedido($pedido))->values(),
            'meta' => ['register_id' => $registerId, 'status' => $status],
        ]);
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'id' => ['nullable', 'uuid'],
            'dining_table_id' => ['required_without:diningTableId', 'uuid'],
            'diningTableId' => ['required_without:dining_table_id', 'uuid'],
            'description' => ['nullable', 'string', 'max:500'],
            'register_id' => ['nullable', 'uuid'],
            'registerId' => ['nullable', 'uuid'],
            'cash_session_id' => ['nullable', 'uuid'],
            'cashSessionId' => ['nullable', 'uuid'],
        ]);

        $mesaId = $dados['dining_table_id'] ?? $dados['diningTableId'];
        $registerId = $this->resolverRegisterIdConsulta(
            $request,
            $dados['register_id'] ?? ($dados['registerId'] ?? null)
        );
        if ($registerId instanceof \Illuminate\Http\JsonResponse) {
            return $registerId;
        }

        $mesa = DiningTable::query()->where('is_active', true)->findOrFail($mesaId);

        $existente = TableOrder::query()
            ->where('dining_table_id', $mesa->id)
            ->where('status', 'OPEN')
            ->first();

        if ($existente) {
            throw ValidationException::withMessages([
                'dining_table_id' => 'Esta mesa já tem uma comanda aberta.',
            ]);
        }

        $pedido = TableOrder::query()->create([
            'id' => $dados['id'] ?? (string) Str::uuid(),
            'dining_table_id' => $mesa->id,
            'register_id' => $registerId,
            'cash_session_id' => $dados['cash_session_id'] ?? ($dados['cashSessionId'] ?? null),
            'user_id' => $request->user()?->id,
            'description' => $dados['description'] ?? null,
            'status' => 'OPEN',
            'opened_at' => now(),
        ]);

        return response()->json(['data' => $this->serializarPedido($pedido->load(['itens', 'diningTable']))], 201);
    }

    public function show(TableOrder $tableOrder)
    {
        return response()->json(['data' => $this->serializarPedido($tableOrder->load(['itens', 'diningTable']))]);
    }

    public function addItems(Request $request, TableOrder $tableOrder)
    {
        $this->garantirPedidoAberto($tableOrder);

        $dados = $request->validate([
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.id' => ['nullable', 'uuid'],
            'itens.*.produtoId' => ['nullable', 'uuid'],
            'itens.*.product_id' => ['nullable', 'uuid'],
            'itens.*.nome' => ['required', 'string', 'max:255'],
            'itens.*.quantidade' => ['required', 'numeric', 'gt:0'],
            'itens.*.precoVenda' => ['required', 'numeric', 'gte:0'],
            'itens.*.precoSemIva' => ['nullable', 'numeric', 'gte:0'],
            'itens.*.ivaPercentual' => ['nullable', 'numeric', 'gte:0'],
            'itens.*.valorIvaUnitario' => ['nullable', 'numeric', 'gte:0'],
            'itens.*.ivaTipo' => ['nullable', 'string', 'max:20'],
            'itens.*.subtotal' => ['required', 'numeric', 'gte:0'],
        ]);

        $sortBase = (int) $tableOrder->itens()->max('sort_order');

        DB::transaction(function () use ($tableOrder, $dados, &$sortBase) {
            foreach ($dados['itens'] as $indice => $item) {
                $productId = $item['produtoId'] ?? ($item['product_id'] ?? null);
                $itemId = $item['id'] ?? null;

                if ($itemId) {
                    $existente = TableOrderItem::query()
                        ->where('table_order_id', $tableOrder->id)
                        ->where('id', $itemId)
                        ->first();

                    if ($existente && $productId && $existente->product_id === $productId) {
                        $novaQtd = (float) $existente->quantidade + (float) $item['quantidade'];
                        $preco = (float) $item['precoVenda'];
                        $existente->update([
                            'quantidade' => $novaQtd,
                            'subtotal' => round($novaQtd * $preco, 2),
                        ]);
                        continue;
                    }
                }

                TableOrderItem::query()->create([
                    'id' => $itemId ?? (string) Str::uuid(),
                    'table_order_id' => $tableOrder->id,
                    'product_id' => $productId,
                    'nome' => $item['nome'],
                    'quantidade' => $item['quantidade'],
                    'preco_venda' => $item['precoVenda'],
                    'preco_sem_iva' => $item['precoSemIva'] ?? null,
                    'iva_percentual' => $item['ivaPercentual'] ?? null,
                    'valor_iva_unitario' => $item['valorIvaUnitario'] ?? null,
                    'iva_tipo' => $item['ivaTipo'] ?? null,
                    'subtotal' => $item['subtotal'],
                    'sort_order' => $sortBase + $indice + 1,
                ]);
            }
        });

        return response()->json(['data' => $this->serializarPedido($tableOrder->fresh(['itens', 'diningTable']))]);
    }

    public function removeItem(TableOrder $tableOrder, TableOrderItem $tableOrderItem)
    {
        $this->garantirPedidoAberto($tableOrder);

        if ($tableOrderItem->table_order_id !== $tableOrder->id) {
            abort(404);
        }

        $tableOrderItem->delete();

        return response()->json(['data' => $this->serializarPedido($tableOrder->fresh(['itens', 'diningTable']))]);
    }

    public function transfer(Request $request, TableOrder $tableOrder)
    {
        $this->garantirPedidoAberto($tableOrder);

        $dados = $request->validate([
            'to_table_id' => ['required_without:toTableId', 'uuid'],
            'toTableId' => ['required_without:to_table_id', 'uuid'],
            'item_ids' => ['nullable', 'array'],
            'itemIds' => ['nullable', 'array'],
            'item_ids.*' => ['uuid'],
            'itemIds.*' => ['uuid'],
        ]);

        $mesaDestinoId = $dados['to_table_id'] ?? $dados['toTableId'];
        $itemIds = $dados['item_ids'] ?? ($dados['itemIds'] ?? []);

        if ($tableOrder->dining_table_id === $mesaDestinoId) {
            throw ValidationException::withMessages([
                'to_table_id' => 'A mesa de destino deve ser diferente da origem.',
            ]);
        }

        $mesaDestino = DiningTable::query()->where('is_active', true)->findOrFail($mesaDestinoId);

        $pedidoDestino = TableOrder::query()
            ->where('dining_table_id', $mesaDestino->id)
            ->where('status', 'OPEN')
            ->first();

        DB::transaction(function () use ($tableOrder, $mesaDestino, $pedidoDestino, $itemIds, $request) {
            if (! $pedidoDestino) {
                $pedidoDestino = TableOrder::query()->create([
                    'id' => (string) Str::uuid(),
                    'dining_table_id' => $mesaDestino->id,
                    'register_id' => $tableOrder->register_id,
                    'cash_session_id' => $tableOrder->cash_session_id,
                    'user_id' => $request->user()?->id,
                    'description' => null,
                    'status' => 'OPEN',
                    'opened_at' => now(),
                ]);
            }

            $query = $tableOrder->itens();
            if (! empty($itemIds)) {
                $query->whereIn('id', $itemIds);
            }

            $itens = $query->get();
            if ($itens->isEmpty()) {
                throw ValidationException::withMessages([
                    'item_ids' => 'Nenhum item seleccionado para transferir.',
                ]);
            }

            $sortBase = (int) $pedidoDestino->itens()->max('sort_order');

            foreach ($itens as $indice => $item) {
                $itemDestino = null;
                if ($item->product_id) {
                    $itemDestino = TableOrderItem::query()
                        ->where('table_order_id', $pedidoDestino->id)
                        ->where('product_id', $item->product_id)
                        ->first();
                }

                if ($itemDestino) {
                    $novaQtd = (float) $itemDestino->quantidade + (float) $item->quantidade;
                    $itemDestino->update([
                        'quantidade' => $novaQtd,
                        'subtotal' => round($novaQtd * (float) $itemDestino->preco_venda, 2),
                    ]);
                } else {
                    TableOrderItem::query()->create([
                        'id' => (string) Str::uuid(),
                        'table_order_id' => $pedidoDestino->id,
                        'product_id' => $item->product_id,
                        'nome' => $item->nome,
                        'quantidade' => $item->quantidade,
                        'preco_venda' => $item->preco_venda,
                        'preco_sem_iva' => $item->preco_sem_iva,
                        'iva_percentual' => $item->iva_percentual,
                        'valor_iva_unitario' => $item->valor_iva_unitario,
                        'iva_tipo' => $item->iva_tipo,
                        'subtotal' => $item->subtotal,
                        'sort_order' => $sortBase + $indice + 1,
                    ]);
                }

                $item->delete();
            }

            if ($tableOrder->itens()->count() === 0) {
                $tableOrder->update([
                    'status' => 'CLOSED',
                    'closed_at' => now(),
                ]);
            }
        });

        return response()->json([
            'data' => [
                'source' => $this->serializarPedido($tableOrder->fresh(['itens', 'diningTable'])),
                'destination' => $this->serializarPedido(
                    TableOrder::query()
                        ->with(['itens', 'diningTable'])
                        ->where('dining_table_id', $mesaDestino->id)
                        ->where('status', 'OPEN')
                        ->first()
                ),
            ],
        ]);
    }

    public function settleItems(Request $request, TableOrder $tableOrder)
    {
        $this->garantirPedidoAberto($tableOrder);

        $dados = $request->validate([
            'sale_id' => ['nullable', 'uuid'],
            'saleId' => ['nullable', 'uuid'],
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.itemId' => ['nullable', 'uuid'],
            'itens.*.item_id' => ['nullable', 'uuid'],
            'itens.*.produtoId' => ['nullable', 'uuid'],
            'itens.*.product_id' => ['nullable', 'uuid'],
            'itens.*.quantidade' => ['required', 'numeric', 'gt:0'],
        ]);

        $saleId = $dados['sale_id'] ?? ($dados['saleId'] ?? null);

        DB::transaction(function () use ($tableOrder, $dados, $saleId) {
            foreach ($dados['itens'] as $entrada) {
                $itemId = $entrada['itemId'] ?? ($entrada['item_id'] ?? null);
                $productId = $entrada['produtoId'] ?? ($entrada['product_id'] ?? null);
                $quantidade = (float) $entrada['quantidade'];

                $item = null;
                if ($itemId) {
                    $item = TableOrderItem::query()
                        ->where('table_order_id', $tableOrder->id)
                        ->where('id', $itemId)
                        ->first();
                }

                if (! $item && $productId) {
                    $item = TableOrderItem::query()
                        ->where('table_order_id', $tableOrder->id)
                        ->where('product_id', $productId)
                        ->first();
                }

                if (! $item) {
                    throw ValidationException::withMessages([
                        'itens' => 'Item não encontrado na comanda.',
                    ]);
                }

                $quantidadeDisponivel = (float) $item->quantidade;
                if ($quantidade > $quantidadeDisponivel + 0.0005) {
                    throw ValidationException::withMessages([
                        'itens' => "Quantidade superior ao disponível em {$item->nome}.",
                    ]);
                }

                $quantidadeRestante = round($quantidadeDisponivel - $quantidade, 3);
                if ($quantidadeRestante <= 0) {
                    $item->delete();
                    continue;
                }

                $preco = (float) $item->preco_venda;
                $item->update([
                    'quantidade' => $quantidadeRestante,
                    'subtotal' => round($quantidadeRestante * $preco, 2),
                ]);
            }

            if ($tableOrder->itens()->count() === 0) {
                $tableOrder->update([
                    'status' => 'CLOSED',
                    'closed_at' => now(),
                    'sale_id' => $saleId,
                ]);
            }
        });

        return response()->json(['data' => $this->serializarPedido($tableOrder->fresh(['itens', 'diningTable']))]);
    }

    public function close(Request $request, TableOrder $tableOrder)
    {
        $this->garantirPedidoAberto($tableOrder);

        $dados = $request->validate([
            'sale_id' => ['nullable', 'uuid'],
            'saleId' => ['nullable', 'uuid'],
        ]);

        $tableOrder->update([
            'status' => 'CLOSED',
            'closed_at' => now(),
            'sale_id' => $dados['sale_id'] ?? ($dados['saleId'] ?? null),
        ]);

        return response()->json(['data' => $this->serializarPedido($tableOrder->fresh(['itens', 'diningTable']))]);
    }

    public function sync(Request $request)
    {
        $dados = $request->validate([
            'pedidos' => ['required', 'array'],
            'pedidos.*.id' => ['required', 'uuid'],
            'pedidos.*.diningTableId' => ['required', 'uuid'],
            'pedidos.*.description' => ['nullable', 'string', 'max:500'],
            'pedidos.*.itens' => ['nullable', 'array'],
            'register_id' => ['nullable', 'uuid'],
            'registerId' => ['nullable', 'uuid'],
            'cash_session_id' => ['nullable', 'uuid'],
            'cashSessionId' => ['nullable', 'uuid'],
        ]);

        $registerId = $this->resolverRegisterIdConsulta(
            $request,
            $dados['register_id'] ?? ($dados['registerId'] ?? null)
        );
        if ($registerId instanceof \Illuminate\Http\JsonResponse) {
            return $registerId;
        }

        $cashSessionId = $dados['cash_session_id'] ?? ($dados['cashSessionId'] ?? null);
        $resultado = [];

        DB::transaction(function () use ($dados, $registerId, $cashSessionId, $request, &$resultado) {
            foreach ($dados['pedidos'] as $payload) {
                $mesa = DiningTable::query()->find($payload['diningTableId']);
                if (! $mesa) {
                    continue;
                }

                $pedido = TableOrder::query()->find($payload['id']);
                if (! $pedido) {
                    $aberto = TableOrder::query()
                        ->where('dining_table_id', $mesa->id)
                        ->where('status', 'OPEN')
                        ->first();

                    if ($aberto) {
                        $pedido = $aberto;
                    } else {
                        $pedido = TableOrder::query()->create([
                            'id' => $payload['id'],
                            'dining_table_id' => $mesa->id,
                            'register_id' => $registerId,
                            'cash_session_id' => $cashSessionId,
                            'user_id' => $request->user()?->id,
                            'description' => $payload['description'] ?? null,
                            'status' => 'OPEN',
                            'opened_at' => now(),
                        ]);
                    }
                }

                if ($pedido->status !== 'OPEN') {
                    continue;
                }

                if (isset($payload['description'])) {
                    $pedido->description = $payload['description'];
                    $pedido->save();
                }

                $itensPayload = is_array($payload['itens'] ?? null) ? $payload['itens'] : [];
                $idsRecebidos = [];

                foreach ($itensPayload as $indice => $item) {
                    $itemId = $item['id'] ?? (string) Str::uuid();
                    $idsRecebidos[] = $itemId;

                    TableOrderItem::query()->updateOrCreate(
                        ['id' => $itemId],
                        [
                            'table_order_id' => $pedido->id,
                            'product_id' => $item['produtoId'] ?? ($item['product_id'] ?? null),
                            'nome' => $item['nome'],
                            'quantidade' => $item['quantidade'],
                            'preco_venda' => $item['precoVenda'] ?? $item['preco_venda'],
                            'preco_sem_iva' => $item['precoSemIva'] ?? ($item['preco_sem_iva'] ?? null),
                            'iva_percentual' => $item['ivaPercentual'] ?? ($item['iva_percentual'] ?? null),
                            'valor_iva_unitario' => $item['valorIvaUnitario'] ?? ($item['valor_iva_unitario'] ?? null),
                            'iva_tipo' => $item['ivaTipo'] ?? ($item['iva_tipo'] ?? null),
                            'subtotal' => $item['subtotal'],
                            'sort_order' => $indice,
                        ]
                    );
                }

                if ($idsRecebidos !== []) {
                    $pedido->itens()->whereNotIn('id', $idsRecebidos)->delete();
                }

                $resultado[] = $this->serializarPedido($pedido->fresh(['itens', 'diningTable']));
            }
        });

        return response()->json(['data' => $resultado]);
    }

    private function garantirPedidoAberto(TableOrder $tableOrder): void
    {
        if ($tableOrder->status !== 'OPEN') {
            throw ValidationException::withMessages([
                'status' => 'A comanda já está fechada.',
            ]);
        }
    }

    private function serializarPedido(?TableOrder $pedido): ?array
    {
        if (! $pedido) {
            return null;
        }

        $mesa = $pedido->relationLoaded('diningTable') ? $pedido->diningTable : null;

        return [
            'id' => $pedido->id,
            'diningTableId' => $pedido->dining_table_id,
            'mesaCodigo' => $mesa?->code,
            'mesaNome' => $mesa?->name,
            'registerId' => $pedido->register_id,
            'cashSessionId' => $pedido->cash_session_id,
            'saleId' => $pedido->sale_id,
            'description' => $pedido->description,
            'status' => $pedido->status,
            'openedAt' => $pedido->opened_at?->toIso8601String(),
            'closedAt' => $pedido->closed_at?->toIso8601String(),
            'subtotal' => round((float) $pedido->itens->sum('subtotal'), 2),
            'total' => round((float) $pedido->itens->sum('subtotal'), 2),
            'itens' => $pedido->itens->map(fn (TableOrderItem $item) => [
                'id' => $item->id,
                'produtoId' => $item->product_id,
                'nome' => $item->nome,
                'quantidade' => (float) $item->quantidade,
                'precoVenda' => (float) $item->preco_venda,
                'precoSemIva' => $item->preco_sem_iva !== null ? (float) $item->preco_sem_iva : null,
                'ivaPercentual' => $item->iva_percentual !== null ? (float) $item->iva_percentual : null,
                'valorIvaUnitario' => $item->valor_iva_unitario !== null ? (float) $item->valor_iva_unitario : null,
                'ivaTipo' => $item->iva_tipo,
                'subtotal' => (float) $item->subtotal,
            ])->values(),
        ];
    }
}
