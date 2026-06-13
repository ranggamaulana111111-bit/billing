<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OltPort extends Model
{
    protected $fillable = [
        'olt_id', 'slot_number', 'port_number', 'port_type', 'status', 'description',
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
