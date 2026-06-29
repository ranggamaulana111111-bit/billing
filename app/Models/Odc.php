<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Odc extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'nama_odc', 'koordinat', 'kapasitas_port',
    ];

    protected $appends = ['name', 'capacity', 'latitude', 'longitude'];

    public function getNameAttribute()
    {
        return $this->nama_odc;
    }

    public function getCapacityAttribute()
    {
        return $this->kapasitas_port;
    }

    public function getLatitudeAttribute()
    {
        if (! $this->koordinat) {
            return null;
        }
        $parts = explode(',', $this->koordinat);

        return count($parts) === 2 ? (float) trim($parts[0]) : null;
    }

    public function getLongitudeAttribute()
    {
        if (! $this->koordinat) {
            return null;
        }
        $parts = explode(',', $this->koordinat);

        return count($parts) === 2 ? (float) trim($parts[1]) : null;
    }

    public function routes()
    {
        return $this->hasMany(OdpRoute::class);
    }

    public function ports()
    {
        return $this->hasMany(OdcPort::class);
    }

    public function odps()
    {
        return $this->hasMany(Odp::class);
    }
}
