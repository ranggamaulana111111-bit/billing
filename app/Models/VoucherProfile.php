<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class VoucherProfile extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id', 'name', 'speed', 'price', 'time_limit',
        'quota_limit', 'validity_days', 'shared_users',
        'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function vouchers()
    {
        return $this->hasMany(Voucher::class, 'voucher_profile_id');
    }
}
