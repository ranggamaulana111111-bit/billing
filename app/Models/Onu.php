<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Onu extends Model
{
    protected $fillable = [
        'olt_port_id', 'customer_id', 'onu_id', 'serial_number',
        'vendor', 'model', 'mac_address', 'status',
        'rx_power', 'tx_power', 'distance', 'uptime',
        'slot_number', 'port_number', 'notes', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'uptime' => 'integer',
            'rx_power' => 'float',
            'tx_power' => 'float',
            'distance' => 'integer',
        ];
    }

    public function oltPort()
    {
        return $this->belongsTo(OltPort::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
