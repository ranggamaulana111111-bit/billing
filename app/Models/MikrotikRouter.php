<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MikrotikRouter extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'host', 'port', 'username',
        'password', 'hotspot_server', 'is_active', 'type',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeByType($query, string $type)
    {
        if ($type === 'general') {
            return $query;
        }

        return $query->where(function ($q) use ($type) {
            $q->where('type', $type)->orWhere('type', 'general');
        });
    }

    public function vouchers()
    {
        return $this->hasMany(Voucher::class, 'router_id');
    }
}
