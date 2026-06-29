<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Setting extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'key', 'value'];

    public static function get(string $key, ?string $default = null, ?int $tenantId = null): ?string
    {
        $query = static::where('key', $key);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } elseif (Auth::hasUser()) {
            $query->where('tenant_id', Auth::user()->tenant_id);
        }

        $setting = $query->first();

        return $setting ? $setting->value : $default;
    }

    public static function set(string $key, ?string $value, ?int $tenantId = null): void
    {
        $tenantId = $tenantId ?? (Auth::hasUser() ? Auth::user()->tenant_id : null);

        static::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => $key],
            ['value' => $value],
        );
    }

    public static function getByUser(int $userId, string $key, ?string $default = null): ?string
    {
        $user = User::find($userId);

        return $user ? static::where('tenant_id', $user->tenant_id)->where('key', $key)->value('value') ?? $default : $default;
    }
}
