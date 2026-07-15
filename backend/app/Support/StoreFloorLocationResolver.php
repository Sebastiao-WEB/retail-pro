<?php

namespace App\Support;

use App\Models\Register;
use App\Models\StockLocation;
use App\Models\User;

/**
 * Local de stock partilhado do estabelecimento (piso de loja / supermercado).
 * Todas as caixas vendem deste pool; o register identifica apenas quem cobrou.
 */
final class StoreFloorLocationResolver
{
    public const CODE = 'LOC-LOJA';

    public const NAME = 'Supermercado - Piso de Loja';

    /** @var list<string> */
    public const LEGACY_REGISTER_LOCATION_CODES = ['LOC-CX01', 'LOC-CX02'];

    public static function findSharedStoreFloor(): ?StockLocation
    {
        return StockLocation::query()
            ->where('code', self::CODE)
            ->where('is_active', true)
            ->first();
    }

    public static function resolveForPos(?User $user = null, ?Register $register = null): ?StockLocation
    {
        $shared = self::findSharedStoreFloor();
        if ($shared instanceof StockLocation) {
            return $shared;
        }

        $register?->loadMissing('stockLocations');
        $user?->loadMissing('sourceLocation');

        return $register?->sourceLocation ?? $user?->sourceLocation;
    }

    /** @return array{id: string, code: string, name: string}|null */
    public static function payloadForPos(?User $user = null, ?Register $register = null): ?array
    {
        $location = self::resolveForPos($user, $register);
        if (! $location instanceof StockLocation) {
            return null;
        }

        return [
            'id' => $location->id,
            'code' => $location->code,
            'name' => $location->name,
        ];
    }

    public static function ensureExists(): StockLocation
    {
        $existing = self::findSharedStoreFloor();
        if ($existing instanceof StockLocation) {
            return $existing;
        }

        return StockLocation::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'code' => self::CODE,
            'name' => self::NAME,
            'type' => 'STORE_FLOOR',
            'is_saleable' => true,
            'is_active' => true,
        ]);
    }
}
