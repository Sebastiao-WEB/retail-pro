<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CashSession;
use App\Models\Register;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CashSessionController extends Controller
{
    public function active(Request $request)
    {
        $dados = $request->validate([
            'register_id' => ['nullable', 'uuid'],
        ]);

        $userId = optional($request->user())->id;

        $sessao = CashSession::query()
            ->where('status', 'OPEN')
            ->when($dados['register_id'] ?? null, fn ($q, $registerId) => $q->where('register_id', $registerId))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->latest('opened_at')
            ->first();

        if (! $sessao) {
            return response()->json([
                'data' => null,
            ]);
        }

        return response()->json([
            'data' => [
                'id' => $sessao->id,
                'register_id' => $sessao->register_id,
                'status' => $sessao->status,
                'opening_balance' => (float) $sessao->opening_balance,
                'opened_at' => optional($sessao->opened_at)->toISOString(),
            ],
        ]);
    }

    public function open(Request $request)
    {
        $dados = $request->validate([
            'register_id' => ['required', 'uuid'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'opened_at' => ['nullable', 'date'],
        ]);

        $register = Register::query()->find($dados['register_id']);
        if (! $register) {
            return response()->json([
                'message' => 'Caixa não encontrado.',
            ], 404);
        }

        $userId = optional($request->user())->id;

        $existeAberta = CashSession::query()
            ->where('register_id', $dados['register_id'])
            ->where('status', 'OPEN')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->first();

        if ($existeAberta) {
            return response()->json([
                'message' => 'Já existe sessão de caixa aberta para este operador/caixa.',
                'data' => [
                    'id' => $existeAberta->id,
                    'register_id' => $existeAberta->register_id,
                    'status' => $existeAberta->status,
                    'opening_balance' => (float) $existeAberta->opening_balance,
                    'opened_at' => optional($existeAberta->opened_at)->toISOString(),
                ],
            ], 409);
        }

        $sessao = CashSession::query()->create([
            'id' => (string) Str::uuid(),
            'register_id' => $dados['register_id'],
            'user_id' => $userId,
            'status' => 'OPEN',
            'opening_balance' => $dados['opening_balance'] ?? 0,
            'opened_at' => $dados['opened_at'] ?? now(),
        ]);

        return response()->json([
            'message' => 'Sessão de caixa aberta com sucesso.',
            'data' => [
                'id' => $sessao->id,
                'register_id' => $sessao->register_id,
                'status' => $sessao->status,
                'opening_balance' => (float) $sessao->opening_balance,
                'opened_at' => optional($sessao->opened_at)->toISOString(),
            ],
        ], 201);
    }

    public function close(Request $request, string $id)
    {
        $dados = $request->validate([
            'closing_balance' => ['required', 'numeric', 'min:0'],
            'closed_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        $sessao = CashSession::query()->find($id);
        if (! $sessao) {
            return response()->json([
                'message' => 'Sessão de caixa não encontrada.',
            ], 404);
        }

        if ($sessao->status !== 'OPEN') {
            return response()->json([
                'message' => 'A sessão de caixa já está encerrada.',
            ], 409);
        }

        $closingBalance = (float) $dados['closing_balance'];
        $openingBalance = (float) $sessao->opening_balance;

        $sessao->update([
            'status' => 'CLOSED',
            'closing_balance' => $closingBalance,
            'difference_amount' => $closingBalance - $openingBalance,
            'closed_at' => $dados['closed_at'] ?? now(),
            'note' => $dados['note'] ?? null,
        ]);

        return response()->json([
            'message' => 'Sessão de caixa fechada com sucesso.',
            'data' => [
                'id' => $sessao->id,
                'status' => $sessao->status,
                'opening_balance' => $openingBalance,
                'closing_balance' => (float) $sessao->closing_balance,
                'difference_amount' => (float) $sessao->difference_amount,
                'closed_at' => optional($sessao->closed_at)->toISOString(),
            ],
        ]);
    }

    public function movements(string $id)
    {
        $sessao = CashSession::query()->find($id);
        if (! $sessao) {
            return response()->json([
                'message' => 'Sessão de caixa não encontrada.',
            ], 404);
        }

        // Placeholder até integrar movimentos financeiros detalhados.
        return response()->json([
            'data' => [],
        ]);
    }
}
