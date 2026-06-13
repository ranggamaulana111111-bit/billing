<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = ['user_id', 'name', 'location', 'phone', 'email', 'package_id', 'odp_point_id', 'pppoe_username', 'due_date', 'status', 'suspended_at'];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function odp()
    {
        return $this->belongsTo(OdpPoint::class, 'odp_point_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function onus()
    {
        return $this->hasMany(Onu::class);
    }
}
