<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Voucher extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'voucher_profile_id', 'voucher_template_id', 'username', 'password', 'duration_hours',
        'price', 'prefix', 'speed', 'quota_limit', 'validity_days', 'shared_users',
        'description', 'hotspot_server',
        'printed_count', 'downloaded', 'uploaded', 'total_traffic',
        'ip_address', 'mac_address', 'last_login_at', 'router_id',
        'status', 'used_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'last_login_at' => 'datetime',
            'price' => 'decimal:2',
        ];
    }

    public function profile()
    {
        return $this->belongsTo(VoucherProfile::class, 'voucher_profile_id');
    }

    public function router()
    {
        return $this->belongsTo(MikrotikRouter::class, 'router_id');
    }

    public function template()
    {
        return $this->belongsTo(VoucherTemplate::class, 'voucher_template_id');
    }

    public static function generate(int $durationHours, int $count = 1, ?array $extra = null): array
    {
        $usernameLen = (int) ($extra['name_length'] ?? Setting::get('voucher_username_length') ?: 8);
        $passwordLen = (int) ($extra['password_length'] ?? Setting::get('voucher_password_length') ?: 6);
        $prefix = $extra['prefix'] ?? '';
        $useNumeric = ($extra['character_type'] ?? 'random') === 'numeric';
        $passwordSameAsUsername = $extra['password_same_as_username'] ?? false;

        $vouchers = [];

        for ($i = 0; $i < $count; $i++) {
            $raw = $useNumeric
                ? substr(str_shuffle('123456789'), 0, $usernameLen)
                : strtoupper(Str::random($usernameLen));
            $username = $prefix ? $prefix.$raw : $raw;
            while (static::where('username', $username)->exists()) {
                $raw = $useNumeric
                    ? substr(str_shuffle('123456789'), 0, $usernameLen)
                    : strtoupper(Str::random($usernameLen));
                $username = $prefix ? $prefix.$raw : $raw;
            }

            $password = $passwordSameAsUsername
                ? $username
                : ($extra['password'] ?? Str::random($passwordLen));

            $data = [
                'username' => $username,
                'password' => $password,
                'duration_hours' => $durationHours,
                'status' => 'active',
                'expires_at' => null, // diisi saat pertama dipakai
                'printed_count' => 0,
                'downloaded' => 0,
                'uploaded' => 0,
                'total_traffic' => 0,
            ];

            if ($extra) {
                if (isset($extra['voucher_profile_id'])) {
                    $data['voucher_profile_id'] = $extra['voucher_profile_id'];
                }
                $data['price'] = $extra['price'] ?? null;
                $data['prefix'] = $extra['prefix'] ?? null;
                $data['speed'] = $extra['speed'] ?? null;
                $data['quota_limit'] = $extra['quota_limit'] ?? null;
                $data['validity_days'] = $extra['validity_days'] ?? null;
                $data['shared_users'] = $extra['shared_users'] ?? 1;
                $data['router_id'] = $extra['router_id'] ?? null;
                $data['voucher_template_id'] = $extra['voucher_template_id'] ?? null;
                $data['description'] = $extra['description'] ?? null;
                $data['hotspot_server'] = $extra['hotspot_server'] ?? null;
            }

            $vouchers[] = static::create($data);
        }

        return $vouchers;
    }
}
