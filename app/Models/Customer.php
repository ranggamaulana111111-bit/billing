<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'name', 'location', 'phone', 'email', 'package_id',
        'odp_point_id', 'odp_id', 'odp_port_id',
        'pppoe_username', 'original_ppp_profile', 'due_date', 'status', 'suspended_at',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function odp()
    {
        return $this->belongsTo(Odp::class, 'odp_id');
    }

    public function odpPort()
    {
        return $this->belongsTo(OdpPort::class, 'odp_port_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function onus()
    {
        return $this->hasMany(Onu::class);
    }
}
