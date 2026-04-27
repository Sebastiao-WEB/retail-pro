<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $search = request()->string('search')->toString();

        $query = Customer::query()->where('is_active', true);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('telefone', 'like', "%{$search}%");
            });
        }

        $clientes = $query->orderBy('nome')->get()->map(fn (Customer $cliente) => [
            'id' => $cliente->id,
            'nome' => $cliente->nome,
            'telefone' => $cliente->telefone,
            'email' => $cliente->email,
            'nuit' => $cliente->nuit,
        ]);

        return response()->json(['data' => $clientes]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'nuit' => ['nullable', 'string', 'max:255'],
        ]);

        $cliente = Customer::create([
            'id' => (string) Str::uuid(),
            ...$dados,
        ]);

        return response()->json([
            'message' => 'Cliente criado com sucesso.',
            'data' => ['id' => $cliente->id],
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        return response()->json([
            'data' => [
                'id' => $customer->id,
                'nome' => $customer->nome,
                'telefone' => $customer->telefone,
                'email' => $customer->email,
                'nuit' => $customer->nuit,
                'isActive' => (bool) $customer->is_active,
            ],
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $dados = $request->validate([
            'nome' => ['sometimes', 'string', 'max:255'],
            'telefone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'nuit' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $customer->fill($dados)->save();

        return response()->json([
            'message' => 'Cliente atualizado com sucesso.',
            'data' => ['id' => $customer->id],
        ]);
    }

    public function destroy(Customer $customer)
    {
        $customer->update(['is_active' => false]);

        return response()->json([
            'message' => 'Cliente desativado com sucesso.',
            'data' => ['id' => $customer->id],
        ]);
    }
}
