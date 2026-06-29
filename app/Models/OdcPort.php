<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OdcPort extends Model
{
    protected $fillable = [
        'odc_id', 'port_number', 'port_type', 'status', 'connected_to_odp_id',
    ];

    public function odc()
    {
        return $this->belongsTo(Odc::class);
    }

    public function connectedOdp()
    {
        return $this->belongsTo(Odp::class, 'connected_to_odp_id');
    }
}
