<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
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
        /** @var User|null $user */
        $user = $request->user();
        $assignedIds = $user ? $user->assignedRegisterIds() : [];

        if ($assignedIds !== []) {
            if ($registerIdInformado && ! in_array($registerIdInformado, $assignedIds, true)) {
                return response()->json([
                    'message' => 'O caixa informado não corresponde ao caixa atribuído ao utilizador.',
                ], 403);
            }

            if ($registerIdInformado && in_array($registerIdInformado, $assignedIds, true)) {
                return $registerIdInformado;
            }

            if ($user?->register_id && in_array($user->register_id, $assignedIds, true)) {
                return $user->register_id;
            }

            return $assignedIds[0];
        }

        if (! $registerIdInformado) {
            return response()->json([
                'message' => 'Utilizador sem caixa atribuído para consultar histórico.',
            ], 422);
        }

        return $registerIdInformado;
    }
}
