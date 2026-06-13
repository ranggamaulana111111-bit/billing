<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Olt extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id', 'name', 'brand', 'model', 'ip_address',
        'ssh_port', 'username', 'password',
        'snmp_community', 'snmp_version', 'snmp_port',
        'location', 'latitude', 'longitude', 'status', 'notes',
        'last_polled_at',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'last_polled_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function ports()
    {
        return $this->hasMany(OltPort::class);
    }
}
