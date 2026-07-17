<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;

trait ResolvesApiClient
{
    /**
     * Sessão mobile POS (login de caixa), vs gestão admin.
     */
    protected function isPosApiClient(): bool
    {
        try {
            $client = auth('api')->payload()->get('client');
            if ($client === 'pos') {
                return true;
            }
            if ($client === 'admin') {
                return false;
            }
        } catch (\Throwable) {
            // Tokens antigos sem claim.
        }

        /** @var User|null $user */
        $user = auth('api')->user();

        return $user?->isCashier() ?? false;
    }
}
