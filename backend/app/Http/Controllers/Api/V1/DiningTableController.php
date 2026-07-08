<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesAssignedRegister;
use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DiningTableController extends Controller
{
    use ResolvesAssignedRegister;

    public function index(Request $request)
    {
        $dados = $request->validate([
            'register_id' => ['nullable', 'uuid'],
            'registerId' => ['nullable', 'uuid'],
        ]);

        $registerId = $this->resolverRegisterIdConsulta(
            $request,
            $dados['register_id'] ?? ($dados['registerId'] ?? null)
        );
        if ($registerId instanceof \Illuminate\Http\JsonResponse) {
            return $registerId;
        }

        $mesas = DiningTable::query()
            ->with(['openOrder.itens'])
            ->where('is_active', true)
            ->where(function ($query) use ($registerId) {
                $query->whereNull('register_id')->orWhere('register_id', $registerId);
            })
            ->orderBy('code')
            ->get();

        return response()->json([
            'data' => $mesas->map(fn (DiningTable $mesa) => $this->serializarMesa($mesa))->values(),
            'meta' => ['register_id' => $registerId],
        ]);
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'id' => ['nullable', 'uuid'],
            'code' => ['required', 'string', 'max:64', 'unique:dining_tables,code'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'register_id' => ['nullable', 'uuid'],
            'registerId' => ['nullable', 'uuid'],
        ]);

        $registerId = $this->resolverRegisterIdConsulta(
            $request,
            $dados['register_id'] ?? ($dados['registerId'] ?? null)
        );
        if ($registerId instanceof \Illuminate\Http\JsonResponse) {
            return $registerId;
        }

        $mesa = DiningTable::query()->create([
            'id' => $dados['id'] ?? (string) Str::uuid(),
            'code' => strtoupper(trim($dados['code'])),
            'name' => $dados['name'] ?? null,
            'description' => $dados['description'] ?? null,
            'register_id' => $registerId,
            'is_active' => true,
        ]);

        return response()->json(['data' => $this->serializarMesa($mesa->fresh(['openOrder.itens']))], 201);
    }

    public function update(Request $request, DiningTable $diningTable)
    {
        $dados = $request->validate([
            'code' => ['sometimes', 'string', 'max:64', Rule::unique('dining_tables', 'code')->ignore($diningTable->id)],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (isset($dados['code'])) {
            $dados['code'] = strtoupper(trim($dados['code']));
        }

        $diningTable->update($dados);

        return response()->json(['data' => $this->serializarMesa($diningTable->fresh(['openOrder.itens']))]);
    }

    private function serializarMesa(DiningTable $mesa): array
    {
        $pedidoAberto = $mesa->relationLoaded('openOrder') ? $mesa->openOrder : null;

        return [
            'id' => $mesa->id,
            'code' => $mesa->code,
            'name' => $mesa->name,
            'description' => $mesa->description,
            'registerId' => $mesa->register_id,
            'isActive' => (bool) $mesa->is_active,
            'occupied' => $pedidoAberto !== null,
            'openOrderId' => $pedidoAberto?->id,
            'createdAt' => $mesa->created_at?->toIso8601String(),
            'updatedAt' => $mesa->updated_at?->toIso8601String(),
        ];
    }
}
