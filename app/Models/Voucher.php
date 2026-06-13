<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Voucher extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = ['user_id', 'username', 'password', 'duration_hours', 'status', 'used_at', 'expires_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public static function generate(int $durationHours, int $count = 1): array
    {
        $usernameLen = (int) (Setting::get('voucher_username_length') ?: 8);
        $passwordLen = (int) (Setting::get('voucher_password_length') ?: 6);

        $vouchers = [];

        for ($i = 0; $i < $count; $i++) {
            $username = strtoupper(Str::random($usernameLen));
            while (static::where('username', $username)->exists()) {
                $username = strtoupper(Str::random($usernameLen));
            }

            $password = Str::random($passwordLen);

            $vouchers[] = static::create([
                'username' => $username,
                'password' => $password,
                'duration_hours' => $durationHours,
                'status' => 'active',
                'expires_at' => now()->addHours($durationHours),
            ]);
        }

        return $vouchers;
    }
}
