<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'type', 'status', 'name', 'brand', 'model', 'serial_number',
        'mac_address', 'ip_address', 'capacity', 'location',
        'latitude', 'longitude', 'attributes', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'attributes' => 'array',
        ];
    }

    public function typeLabel(): string
    {
        return strtoupper($this->type);
    }
}
