<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\StoreFloorLocationResolver;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'id',
    'name',
    'username',
    'email',
    'password',
    'role',
    'caixa_atribuido',
    'register_id',
    'source_location_id',
    'is_active',
])]
#[Hidden(['password', 'remember_token', 'two_factor_recovery_codes', 'two_factor_secret'])]
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, Notifiable, TwoFactorAuthenticatable;

    protected string $guard_name = 'web';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'role' => $this->role,
            'register_id' => $this->register_id,
            'source_location_id' => $this->source_location_id,
        ];
    }

    public function register()
    {
        return $this->belongsTo(Register::class);
    }

    public function registers()
    {
        return $this->belongsToMany(Register::class, 'register_user');
    }

    /** @return \Illuminate\Support\Collection<int, Register> */
    public function assignedRegisters()
    {
        $registers = $this->registers()->where('registers.is_active', true)->orderBy('registers.name')->get();
        if ($registers->isNotEmpty()) {
            return $registers;
        }

        if ($this->register_id) {
            $register = Register::query()->where('id', $this->register_id)->where('is_active', true)->first();
            if ($register) {
                return collect([$register]);
            }
        }

        return collect();
    }

    /** @return array<int, string> */
    public function assignedRegisterIds(): array
    {
        return $this->assignedRegisters()->pluck('id')->all();
    }

    public function syncAssignedRegisters(array $registerIds): void
    {
        $ids = collect($registerIds)->filter()->unique()->values()->all();
        $this->registers()->sync($ids);

        if ($ids !== []) {
            if (! $this->register_id || ! in_array($this->register_id, $ids, true)) {
                $this->register_id = $ids[0];
            }
            $this->syncCaixaAtribuido($ids);
        } else {
            $this->register_id = null;
            $this->caixa_atribuido = null;
        }
    }

    public function syncCaixaAtribuido(?array $registerIds = null): void
    {
        $ids = $registerIds ?? $this->assignedRegisterIds();
        if ($ids === []) {
            $this->caixa_atribuido = null;

            return;
        }

        $nomes = Register::query()->whereIn('id', $ids)->orderBy('name')->pluck('name');
        $this->caixa_atribuido = $nomes->join(', ');
    }

    public function applyActiveRegister(Register $register): void
    {
        $this->register_id = $register->id;
        $this->syncCaixaAtribuido([$register->id]);
        $this->syncSourceLocationFromRegister($register->id);
    }

    public function syncSourceLocationFromRegister(?string $registerId): void
    {
        $shared = StoreFloorLocationResolver::findSharedStoreFloor();
        if ($shared) {
            $this->source_location_id = $shared->id;

            return;
        }

        if (! $registerId) {
            return;
        }

        $register = Register::query()->with('stockLocations')->find($registerId);
        $location = $register?->sourceLocation;
        if ($location) {
            $this->source_location_id = $location->id;
        }
    }

    public function sourceLocation()
    {
        return $this->belongsTo(StockLocation::class, 'source_location_id');
    }

    public function isCashier(): bool
    {
        if (strcasecmp((string) ($this->role ?? ''), 'CASHIER') === 0) {
            return true;
        }

        return $this->hasRole('CASHIER');
    }
}
