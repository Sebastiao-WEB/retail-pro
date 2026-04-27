<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = Sale::query()->with('itens')->latest('data');

        if ($registerId = request('register_id')) {
            $query->where('register_id', $registerId);
        }

        $sales = $query->get()->map(function (Sale $sale) {
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
                'itens' => $sale->itens->map(fn (SaleItem $item) => [
                    'produtoId' => $item->produto_id,
                    'nome' => $item->nome,
                    'quantidade' => (float) $item->quantidade,
                    'precoVenda' => (float) $item->preco_venda,
                    'precoSemIva' => (float) $item->preco_sem_iva,
                    'ivaPercentual' => (float) $item->iva_percentual,
                    'valorIvaUnitario' => (float) $item->valor_iva_unitario,
                    'subtotal' => (float) $item->subtotal,
                ])->values(),
            ];
        });

        return response()->json(['data' => $sales]);
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
        ]);

        $sale = DB::transaction(function () use ($dados) {
            $sale = Sale::create([
                'id' => $dados['id'] ?? (string) Str::uuid(),
                'referencia' => $dados['referencia'] ?? ('VD-'.now()->format('Ymd-His')),
                'register_id' => $dados['register_id'] ?? ($dados['registerId'] ?? null),
                'source_location_id' => $dados['source_location_id'] ?? ($dados['sourceLocationId'] ?? null),
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

            foreach ($dados['itens'] as $item) {
                SaleItem::create([
                    'id' => (string) Str::uuid(),
                    'sale_id' => $sale->id,
                    'produto_id' => $item['produtoId'] ?? null,
                    'nome' => $item['nome'],
                    'quantidade' => $item['quantidade'],
                    'preco_venda' => $item['precoVenda'],
                    'preco_sem_iva' => $item['precoSemIva'] ?? 0,
                    'iva_percentual' => $item['ivaPercentual'] ?? 0,
                    'valor_iva_unitario' => $item['valorIvaUnitario'] ?? 0,
                    'subtotal' => $item['subtotal'],
                ]);
            }

            return $sale;
        });

        return response()->json([
            'message' => 'Venda registada com sucesso.',
            'data' => [
                'id' => $sale->id,
                'referencia' => $sale->referencia,
                'status' => 'COMPLETED',
            ],
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Sale $sale)
    {
        $sale->load('itens');

        return response()->json([
            'data' => [
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
                'itens' => $sale->itens->map(fn (SaleItem $item) => [
                    'produtoId' => $item->produto_id,
                    'nome' => $item->nome,
                    'quantidade' => (float) $item->quantidade,
                    'precoVenda' => (float) $item->preco_venda,
                    'precoSemIva' => (float) $item->preco_sem_iva,
                    'ivaPercentual' => (float) $item->iva_percentual,
                    'valorIvaUnitario' => (float) $item->valor_iva_unitario,
                    'subtotal' => (float) $item->subtotal,
                ])->values(),
            ],
        ]);
    }

    public function update(Request $request, string $id) {}
    public function destroy(string $id) {}
}
