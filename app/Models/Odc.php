<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Odc extends Model
{
    protected $fillable = [
        'name', 'address', 'latitude', 'longitude', 'status', 'capacity', 'notes',
    ];

    public function routes()
    {
        return $this->hasMany(OdpRoute::class);
    }
}
