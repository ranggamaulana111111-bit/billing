<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Setting extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'key', 'value'];

    /**
     * In-memory per-request store (one query per tenant per request).
     *
     * @var array<int|string|null, array<string, string>>
     */
    protected static array $store = [];

    public static function get(string $key, ?string $default = null, ?int $tenantId = null): ?string
    {
        $tenantId = $tenantId ?? (Auth::hasUser() ? Auth::user()->tenant_id : null);

        $all = static::allForTenant($tenantId);

        return $all[$key] ?? $default;
    }

    public static function set(string $key, ?string $value, ?int $tenantId = null): void
    {
        $tenantId = $tenantId ?? (Auth::hasUser() ? Auth::user()->tenant_id : null);

        static::withoutGlobalScope('tenant_id')
            ->updateOrCreate(
                ['tenant_id' => $tenantId, 'key' => $key],
                ['value' => $value],
            );

        unset(static::$store[$tenantId]);
    }

    public static function getByUser(int $userId, string $key, ?string $default = null): ?string
    {
        $user = User::find($userId);
        if (! $user) {
            return $default;
        }

        return static::get($key, $default, $user->tenant_id);
    }

    public static function flushStore(?int $tenantId = null): void
    {
        if ($tenantId === null) {
            static::$store = [];
        } else {
            unset(static::$store[$tenantId]);
        }
    }

    protected static function allForTenant(?int $tenantId): array
    {
        if (! array_key_exists($tenantId, static::$store)) {
            $query = static::query();

            if ($tenantId !== null) {
                $query->withoutGlobalScope('tenant_id')->where('tenant_id', $tenantId);
            }

            static::$store[$tenantId] = $query->pluck('value', 'key')->toArray();
        }

        return static::$store[$tenantId];
    }
}
