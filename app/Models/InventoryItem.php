<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'category', 'name', 'type', 'brand', 'serial_number',
        'port_count', 'pon_port_count', 'cable_type', 'unit', 'stock', 'description',
    ];

    public function transactions()
    {
        return $this->hasMany(InventoryTransaction::class, 'inventory_item_id');
    }

    public function getFormattedStockAttribute(): string
    {
        return number_format($this->stock, 0, ',', '.');
    }

    public const CATEGORIES = [
        'mikrotik' => 'MikroTik',
        'olt' => 'OLT',
        'otb' => 'OTB (Optical Termination Box)',
        'odc' => 'ODC (Optical Distribution Cabinet)',
        'odp' => 'ODP (Optical Distribution Point)',
        'kabel_rj45' => 'Kabel RJ45',
        'kabel' => 'Kabel',
        'ont_modem' => 'ONT / Modem',
        'roset' => 'Roset',
        'esklem' => 'Esklem',
    ];

    public const UNITS = [
        'pcs' => 'Pcs',
        'set' => 'Set',
        'meter' => 'Meter',
        'roll' => 'Roll',
        'box' => 'Box',
        'unit' => 'Unit',
    ];

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function getUnitLabelAttribute(): string
    {
        return self::UNITS[$this->unit] ?? $this->unit;
    }
}
