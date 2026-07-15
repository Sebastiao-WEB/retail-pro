<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesAssignedRegister;
use App\Models\CashSession;
use App\Models\Register;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CashSessionController extends Controller
{
    use ResolvesAssignedRegister;

    public function index(Request $request)
    {
        $dados = $request->validate([
            'register_id' => ['nullable', 'uuid'],
            'status' => ['nullable', 'in:OPEN,CLOSED'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $registerId = $this->resolverRegisterIdConsulta($request, $dados['register_id'] ?? null);
        if ($registerId instanceof \Illuminate\Http\JsonResponse) {
            return $registerId;
        }

        $perPage = min(50, max(1, (int) ($dados['per_page'] ?? 10)));
        $search = (string) ($dados['search'] ?? '');
        $status = $dados['status'] ?? null;

        $userId = optional($request->user())->id;
        $verTodos = $this->podeVerTodosFechosCaixa($request);

        $query = CashSession::query()
            ->with(['register', 'user'])
            ->where('register_id', $registerId)
            ->when(! $verTodos && $userId, fn ($q) => $q->where('user_id', $userId))
            ->when($status, fn ($q, $valor) => $q->where('status', $valor))
            ->when($search !== '', function ($q) use ($search, $status) {
                $q->where(function ($inner) use ($search, $status) {
                    if ($status === 'CLOSED') {
                        $inner->where('note', 'like', "%{$search}%");
                    }
                    $inner->orWhereHas('register', fn ($reg) => $reg
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
                });
            });

        if ($status === 'CLOSED') {
            $query->latest('closed_at');
        } else {
            $query->latest('created_at');
        }

        $paginado = $query->paginate($perPage);

        return response()->json([
            'data' => collect($paginado->items())->map(fn (CashSession $sessao) => $this->serializarSessao($sessao))->values(),
            'meta' => [
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'per_page' => $paginado->perPage(),
                'total' => $paginado->total(),
                'register_id' => $registerId,
            ],
        ]);
    }

    private function serializarSessao(CashSession $sessao): array
    {
        $snapshot = is_array($sessao->report_snapshot) ? $sessao->report_snapshot : [];

        return [
            'id' => $sessao->id,
            'registerId' => $sessao->register_id,
            'registerName' => $sessao->register?->name ?? ($snapshot['caixa'] ?? null),
            'operatorName' => $sessao->user?->name ?? ($snapshot['utilizador'] ?? null),
            'status' => $sessao->status,
            'openingBalance' => (float) $sessao->opening_balance,
            'closingBalance' => $sessao->closing_balance !== null ? (float) $sessao->closing_balance : null,
            'differenceAmount' => $sessao->difference_amount !== null ? (float) $sessao->difference_amount : null,
            'openedAt' => optional($sessao->opened_at)->toISOString(),
            'closedAt' => optional($sessao->closed_at)->toISOString(),
            'createdAt' => optional($sessao->created_at)->toISOString(),
            'userId' => $sessao->user_id,
            'note' => $sessao->note,
            'reportSnapshot' => $sessao->report_snapshot,
        ];
    }

    private function podeVerTodosFechosCaixa(Request $request): bool
    {
        $authUser = $request->user('api');
        abort_unless($authUser, 401);

        $allowedByRole = in_array((string) ($authUser->role ?? ''), ['ADMIN', 'MANAGER'], true);
        $allowedByPermission = $authUser->can('cash_sessions.view');

        return $allowedByRole || $allowedByPermission;
    }

    public function active(Request $request)
    {
        $dados = $request->validate([
            'register_id' => ['nullable', 'uuid'],
        ]);

        $registerId = $this->resolverRegisterIdConsulta($request, $dados['register_id'] ?? null);
        if ($registerId instanceof \Illuminate\Http\JsonResponse) {
            return $registerId;
        }

        $userId = optional($request->user())->id;

        $sessao = CashSession::query()
            ->where('status', 'OPEN')
            ->where('register_id', $registerId)
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

        $registerId = $this->resolverRegisterIdConsulta($request, $dados['register_id']);
        if ($registerId instanceof \Illuminate\Http\JsonResponse) {
            return $registerId;
        }

        $userId = optional($request->user())->id;

        $existeAberta = CashSession::query()
            ->where('register_id', $registerId)
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
            'register_id' => $registerId,
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
            'report_snapshot' => ['nullable', 'array'],
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
            'report_snapshot' => $dados['report_snapshot'] ?? null,
        ]);

        return response()->json([
            'message' => 'Sessão de caixa fechada com sucesso.',
            'data' => $this->serializarSessao($sessao->fresh(['register'])),
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
