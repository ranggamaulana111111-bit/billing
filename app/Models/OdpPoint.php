<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class OdpPoint extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'odp_route_id', 'name', 'address',
        'latitude', 'longitude', 'status',
        'port_capacity', 'port_used',
    ];

    public function route()
    {
        return $this->belongsTo(OdpRoute::class, 'odp_route_id');
    }

    public function customers()
    {
        return $this->hasMany(Customer::class, 'odp_point_id');
    }
}
