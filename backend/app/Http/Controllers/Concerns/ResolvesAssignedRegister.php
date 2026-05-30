<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait ResolvesAssignedRegister
{
    /**
     * Garante que a consulta fica limitada ao caixa atribuído ao utilizador autenticado.
     *
     * @return string|\Illuminate\Http\JsonResponse
     */
    protected function resolverRegisterIdConsulta(Request $request, ?string $registerIdInformado)
    {
        $registerAtribuido = optional($request->user())->register_id;

        if ($registerAtribuido) {
            if ($registerIdInformado && $registerIdInformado !== $registerAtribuido) {
                return response()->json([
                    'message' => 'O caixa informado não corresponde ao caixa atribuído ao utilizador.',
                ], 403);
            }

            return $registerAtribuido;
        }

        if (! $registerIdInformado) {
            return response()->json([
                'message' => 'Utilizador sem caixa atribuído para consultar histórico.',
            ], 422);
        }

        return $registerIdInformado;
    }
}
