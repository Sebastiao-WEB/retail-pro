<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ApiTwoFactorChallengeService
{
    public const TTL_SECONDS = 300;

    private const CACHE_PREFIX = 'api_2fa_challenge:';

    public function create(string $userId, string $registerId): string
    {
        $token = (string) Str::uuid();

        Cache::put($this->cacheKey($token), [
            'user_id' => $userId,
            'register_id' => $registerId,
        ], self::TTL_SECONDS);

        return $token;
    }

    /** @return array{user_id: string, register_id: string}|null */
    public function get(string $token): ?array
    {
        $data = Cache::get($this->cacheKey($token));

        return is_array($data) ? $data : null;
    }

    public function forget(string $token): void
    {
        Cache::forget($this->cacheKey($token));
    }

    private function cacheKey(string $token): string
    {
        return self::CACHE_PREFIX.$token;
    }
}
