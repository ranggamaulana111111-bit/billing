<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'key', 'value'];

    public static function get(string $key, ?string $default = null, ?int $tenantId = null): ?string
    {
        $tenantId = $tenantId ?? (Auth::hasUser() ? Auth::user()->tenant_id : null);
        $cacheKey = "setting_{$tenantId}_{$key}";

        return Cache::remember($cacheKey, 3600, function () use ($key, $tenantId, $default) {
            $query = static::where('key', $key);
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
            $setting = $query->first();

            return $setting ? $setting->value : $default;
        });
    }

    public static function set(string $key, ?string $value, ?int $tenantId = null): void
    {
        $tenantId = $tenantId ?? (Auth::hasUser() ? Auth::user()->tenant_id : null);

        static::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => $key],
            ['value' => $value],
        );

        Cache::forget("setting_{$tenantId}_{$key}");
    }

    public static function getByUser(int $userId, string $key, ?string $default = null): ?string
    {
        $user = User::find($userId);
        if (! $user) {
            return $default;
        }

        return static::get($key, $default, $user->tenant_id);
    }
}
