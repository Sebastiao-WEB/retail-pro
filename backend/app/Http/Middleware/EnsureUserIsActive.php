<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public const SUSPENDED_MESSAGE = 'Conta suspensa. Contacte o administrador do sistema.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $user->refresh();
        }

        if (! $user || $user->is_active) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            try {
                auth('api')->logout();
            } catch (\Throwable) {
                // Token inválido ou já expirado.
            }

            return response()->json([
                'message' => self::SUSPENDED_MESSAGE,
                'account_suspended' => true,
            ], 403);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->withErrors(['username' => self::SUSPENDED_MESSAGE]);
    }
}
