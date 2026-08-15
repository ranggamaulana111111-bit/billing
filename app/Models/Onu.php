<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Onu extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'olt_port_id', 'odp_port_id', 'customer_id', 'onu_id', 'serial_number', 'caller_id',
        'vendor', 'model', 'mac_address', 'status',
        'rx_power', 'tx_power', 'distance', 'uptime',
        'slot_number', 'port_number', 'notes', 'last_seen_at',
        'acs_device_id', 'acs_status', 'acs_last_inform', 'acs_ip', 'acs_manufacturer',
        'acs_product_class', 'acs_hardware_version', 'acs_software_version',
        'acs_connection_request_url', 'acs_username', 'acs_password',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'uptime' => 'integer',
            'rx_power' => 'float',
            'tx_power' => 'float',
            'distance' => 'integer',
            'acs_last_inform' => 'datetime',
        ];
    }

    public function oltPort()
    {
        return $this->belongsTo(OltPort::class);
    }

    public function odpPort()
    {
        return $this->belongsTo(OdpPort::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopeFromOlt($query)
    {
        return $query->whereNot('onu_id', 'like', 'mikrotik-%');
    }
}
