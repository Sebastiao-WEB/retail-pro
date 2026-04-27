<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Register;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    public function index()
    {
        $data = Register::query()->orderBy('name')->get()->map(fn (Register $item) => [
            'id' => $item->id,
            'code' => $item->code,
            'name' => $item->name,
            'isActive' => (bool) $item->is_active,
        ]);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:registers,code'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $register = Register::query()->create([
            'code' => $dados['code'],
            'name' => $dados['name'],
            'is_active' => $dados['is_active'] ?? true,
        ]);

        return response()->json(['message' => 'Caixa criado.', 'data' => ['id' => $register->id]], 201);
    }

    public function update(Request $request, Register $register)
    {
        $dados = $request->validate([
            'code' => ['sometimes', 'string', 'max:255', 'unique:registers,code,'.$register->id.',id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $register->fill($dados)->save();

        return response()->json(['message' => 'Caixa atualizado.', 'data' => ['id' => $register->id]]);
    }
}
