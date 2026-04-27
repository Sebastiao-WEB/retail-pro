<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StockLocation;
use Illuminate\Http\Request;

class StockLocationController extends Controller
{
    public function index()
    {
        $data = StockLocation::query()->with('register')->orderBy('name')->get()->map(fn (StockLocation $item) => [
            'id' => $item->id,
            'registerId' => $item->register_id,
            'registerName' => $item->register?->name,
            'code' => $item->code,
            'name' => $item->name,
            'type' => $item->type,
            'isSaleable' => (bool) $item->is_saleable,
            'isActive' => (bool) $item->is_active,
        ]);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'register_id' => ['nullable', 'uuid', 'exists:registers,id'],
            'code' => ['required', 'string', 'max:255', 'unique:stock_locations,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:STORE_FLOOR,WAREHOUSE,DAMAGE,RETURN_AREA,TRANSIT'],
            'is_saleable' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $location = StockLocation::query()->create([
            ...$dados,
            'is_saleable' => $dados['is_saleable'] ?? true,
            'is_active' => $dados['is_active'] ?? true,
        ]);

        return response()->json(['message' => 'Localização criada.', 'data' => ['id' => $location->id]], 201);
    }

    public function update(Request $request, StockLocation $stockLocation)
    {
        $dados = $request->validate([
            'register_id' => ['sometimes', 'nullable', 'uuid', 'exists:registers,id'],
            'code' => ['sometimes', 'string', 'max:255', 'unique:stock_locations,code,'.$stockLocation->id.',id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:STORE_FLOOR,WAREHOUSE,DAMAGE,RETURN_AREA,TRANSIT'],
            'is_saleable' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $stockLocation->fill($dados)->save();

        return response()->json(['message' => 'Localização atualizada.', 'data' => ['id' => $stockLocation->id]]);
    }
}
