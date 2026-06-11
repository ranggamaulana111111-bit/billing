<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Setting extends Model
{
    use BelongsToUser;

    protected $fillable = ['user_id', 'key', 'value'];

    public static function get(string $key, ?string $default = null, ?int $userId = null): ?string
    {
        $query = static::where('key', $key);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $setting = $query->first();

        return $setting ? $setting->value : $default;
    }

    public static function set(string $key, ?string $value, ?int $userId = null): void
    {
        $userId = $userId ?? Auth::id();

        static::updateOrCreate(
            ['user_id' => $userId, 'key' => $key],
            ['value' => $value],
        );
    }

    public static function getByUser(int $userId, string $key, ?string $default = null): ?string
    {
        return static::where('user_id', $userId)->where('key', $key)->value('value') ?? $default;
    }
}
