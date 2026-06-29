<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Olt extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'brand', 'model', 'ip_address',
        'ssh_port', 'username', 'password',
        'jump_host', 'jump_port', 'jump_username', 'jump_password',
        'snmp_community', 'snmp_version', 'snmp_port',
        'location', 'latitude', 'longitude', 'status', 'notes',
        'last_polled_at',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'jump_password' => 'encrypted',
            'last_polled_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function hasJumpHost(): bool
    {
        return filled($this->jump_host) && filled($this->jump_username);
    }

    public function usesMikrotikProxy(): bool
    {
        if (! $this->hasJumpHost()) {
            return false;
        }

        $mikrotikHost = Setting::get('mikrotik_host');

        return $mikrotikHost && $this->jump_host === $mikrotikHost;
    }

    public function ports()
    {
        return $this->hasMany(OltPort::class);
    }
}
