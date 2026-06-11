<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Odc extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id', 'name', 'address', 'latitude', 'longitude', 'status', 'capacity', 'notes',
    ];

    public function routes()
    {
        return $this->hasMany(OdpRoute::class);
    }
}
