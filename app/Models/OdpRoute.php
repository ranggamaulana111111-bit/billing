<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class OdpRoute extends Model
{
    use BelongsToUser;

    protected $fillable = ['user_id', 'odc_id', 'name', 'description', 'color', 'coordinates'];

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
