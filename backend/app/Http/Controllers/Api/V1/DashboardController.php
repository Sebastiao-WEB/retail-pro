<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardSummaryService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request, DashboardSummaryService $dashboard)
    {
        $this->authorizeDashboard($request);

        $dados = $request->validate([
            'period' => ['nullable', 'string', 'in:today,7d,30d,month'],
            'register_id' => ['nullable', 'string', 'uuid'],
            'sales_page' => ['nullable', 'integer', 'min:1'],
            'sales_per_page' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $period = $dados['period'] ?? '7d';
        $registerId = filled($dados['register_id'] ?? null) ? $dados['register_id'] : null;

        return response()->json([
            'data' => array_merge(
                $dashboard->build($period, $registerId),
                [
                    'recentSales' => $dashboard->recentSales(
                        $registerId,
                        (int) ($dados['sales_page'] ?? 1),
                        (int) ($dados['sales_per_page'] ?? 5)
                    ),
                ]
            ),
        ]);
    }

    public function recentSales(Request $request, DashboardSummaryService $dashboard)
    {
        $this->authorizeDashboard($request);

        $dados = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:20'],
            'register_id' => ['nullable', 'string', 'uuid'],
        ]);

        $registerId = filled($dados['register_id'] ?? null) ? $dados['register_id'] : null;

        return response()->json([
            'data' => $dashboard->recentSales(
                $registerId,
                (int) ($dados['page'] ?? 1),
                (int) ($dados['per_page'] ?? 5)
            ),
        ]);
    }

    private function authorizeDashboard(Request $request): void
    {
        $user = $request->user('api');
        abort_unless($user, 401);

        $allowedByRole = in_array((string) ($user->role ?? ''), ['ADMIN', 'MANAGER'], true);
        $allowedByPermission = $user->can('dashboard.view');

        abort_unless($allowedByRole || $allowedByPermission, 403);
    }
}
