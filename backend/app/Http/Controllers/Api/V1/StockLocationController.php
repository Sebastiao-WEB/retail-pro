<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StockLocation;
use Illuminate\Http\Request;

class StockLocationController extends Controller
{
    public function index()
    {
        $data = StockLocation::query()
            ->with('registers')
            ->orderBy('name')
            ->get()
            ->map(fn (StockLocation $item) => [
                'id' => $item->id,
                'registerIds' => $item->registers->pluck('id')->all(),
                'registerNames' => $item->registers->pluck('name')->all(),
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
            'register_ids' => ['nullable', 'array'],
            'register_ids.*' => ['uuid', 'exists:registers,id'],
            'registerIds' => ['nullable', 'array'],
            'registerIds.*' => ['uuid', 'exists:registers,id'],
            'code' => ['required', 'string', 'max:255', 'unique:stock_locations,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:STORE_FLOOR,WAREHOUSE,DAMAGE,RETURN_AREA,TRANSIT'],
            'is_saleable' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $registerIds = collect($dados['register_ids'] ?? $dados['registerIds'] ?? [])
            ->filter()
            ->unique()
            ->values()
            ->all();

        unset($dados['register_ids'], $dados['registerIds']);

        $location = StockLocation::query()->create([
            ...$dados,
            'is_saleable' => $dados['is_saleable'] ?? true,
            'is_active' => $dados['is_active'] ?? true,
        ]);

        $location->registers()->sync($registerIds);

        return response()->json(['message' => 'Localização criada.', 'data' => ['id' => $location->id]], 201);
    }

    public function update(Request $request, StockLocation $stockLocation)
    {
        $dados = $request->validate([
            'register_ids' => ['sometimes', 'nullable', 'array'],
            'register_ids.*' => ['uuid', 'exists:registers,id'],
            'registerIds' => ['sometimes', 'nullable', 'array'],
            'registerIds.*' => ['uuid', 'exists:registers,id'],
            'code' => ['sometimes', 'string', 'max:255', 'unique:stock_locations,code,'.$stockLocation->id.',id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:STORE_FLOOR,WAREHOUSE,DAMAGE,RETURN_AREA,TRANSIT'],
            'is_saleable' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $registerIds = null;
        if (array_key_exists('register_ids', $dados) || array_key_exists('registerIds', $dados)) {
            $registerIds = collect($dados['register_ids'] ?? $dados['registerIds'] ?? [])
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        unset($dados['register_ids'], $dados['registerIds']);

        $stockLocation->fill($dados)->save();

        if (is_array($registerIds)) {
            $stockLocation->registers()->sync($registerIds);
        }

        return response()->json(['message' => 'Localização atualizada.', 'data' => ['id' => $stockLocation->id]]);
    }
}
