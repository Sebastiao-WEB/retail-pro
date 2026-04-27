<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PurchaseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $compras = Purchase::query()->latest('data')->get()->map(fn (Purchase $purchase) => [
            'id' => $purchase->id,
            'fornecedor' => $purchase->fornecedor,
            'total' => (float) $purchase->total,
            'data' => optional($purchase->data)->toISOString(),
            'itens' => $purchase->itens ?? [],
        ]);

        return response()->json(['data' => $compras]);
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'id' => ['nullable', 'uuid'],
            'fornecedor' => ['required', 'string', 'max:255'],
            'total' => ['required', 'numeric'],
            'data' => ['nullable', 'date'],
            'itens' => ['nullable', 'array'],
        ]);

        $compra = Purchase::create([
            'id' => $dados['id'] ?? (string) Str::uuid(),
            'fornecedor' => $dados['fornecedor'],
            'total' => $dados['total'],
            'data' => $dados['data'] ?? now(),
            'itens' => $dados['itens'] ?? [],
        ]);

        return response()->json([
            'message' => 'Compra registada com sucesso.',
            'data' => ['id' => $compra->id],
        ], 201);
    }

    public function update(Request $request, Purchase $purchase)
    {
        $dados = $request->validate([
            'fornecedor' => ['sometimes', 'string', 'max:255'],
            'total' => ['sometimes', 'numeric'],
            'data' => ['sometimes', 'date'],
            'itens' => ['sometimes', 'array'],
        ]);

        $purchase->fill($dados)->save();

        return response()->json([
            'message' => 'Compra atualizada com sucesso.',
            'data' => ['id' => $purchase->id],
        ]);
    }

    public function destroy(Purchase $purchase)
    {
        $purchase->delete();

        return response()->json([
            'message' => 'Compra removida com sucesso.',
            'data' => ['id' => $purchase->id],
        ]);
    }
}
