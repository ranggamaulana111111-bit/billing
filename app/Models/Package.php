<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory, BelongsToUser;

    protected $fillable = ['user_id', 'name', 'speed', 'description', 'price', 'billing_cycle', 'mikrotik_profile', 'is_active'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }
}
