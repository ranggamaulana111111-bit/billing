<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class OltPort extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'olt_id', 'slot_number', 'port_number', 'port_type', 'status', 'description',
    ];

    public function olt()
    {
        return $this->belongsTo(Olt::class);
    }

    public function onus()
    {
        return $this->hasMany(Onu::class);
    }
}
