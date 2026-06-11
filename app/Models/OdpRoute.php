<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OdpRoute extends Model
{
    protected $fillable = ['odc_id', 'name', 'description', 'color', 'coordinates'];

    protected function casts(): array
    {
        return [
            'coordinates' => 'array',
        ];
    }

    public function points()
    {
        return $this->hasMany(OdpPoint::class);
    }

    public function odc()
    {
        return $this->belongsTo(Odc::class);
    }
}
