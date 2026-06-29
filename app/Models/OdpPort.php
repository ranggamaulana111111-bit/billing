<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OdpPort extends Model
{
    protected $fillable = [
        'odp_id', 'port_number', 'status',
    ];

    public function odp()
    {
        return $this->belongsTo(Odp::class);
    }

    public function customer()
    {
        return $this->hasOne(Customer::class, 'odp_port_id');
    }
}
