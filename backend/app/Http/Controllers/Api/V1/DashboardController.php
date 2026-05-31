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
        ]);

        $period = $dados['period'] ?? '7d';
        $registerId = filled($dados['register_id'] ?? null) ? $dados['register_id'] : null;

        return response()->json([
            'data' => $dashboard->build($period, $registerId),
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
