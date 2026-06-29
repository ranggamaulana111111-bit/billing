<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MikrotikRouter extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'host', 'port', 'username',
        'password', 'hotspot_server', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function vouchers()
    {
        return $this->hasMany(Voucher::class, 'router_id');
    }
}
