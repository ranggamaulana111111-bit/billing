<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Odp extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'odc_id', 'nama_odp', 'koordinat', 'kapasitas_port',
        'kabel_tube_color', 'kabel_core_number', 'kondisi_jalur',
    ];

    protected $appends = ['name', 'address', 'latitude', 'longitude'];

    public function getNameAttribute()
    {
        return $this->nama_odp;
    }

    public function getAddressAttribute()
    {
        return $this->koordinat;
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

    public function odc()
    {
        return $this->belongsTo(Odc::class);
    }

    public function ports()
    {
        return $this->hasMany(OdpPort::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function availablePortsCount()
    {
        return $this->ports()->where('status', 'available')->count();
    }

    public function usedPortsCount()
    {
        return $this->ports()->where('status', 'used')->count();
    }

    public function brokenPortsCount()
    {
        return $this->ports()->where('status', 'broken')->count();
    }
}
