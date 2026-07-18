<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'inventory_item_id', 'type', 'quantity', 'condition', 'date',
        'notes', 'customer_id', 'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->type === 'in' ? 'Masuk' : 'Keluar';
    }

    public const CONDITIONS = [
        'baik' => 'Baik',
        'terpakai' => 'Terpakai',
        'rusak' => 'Rusak',
    ];

    public function getConditionLabelAttribute(): string
    {
        return self::CONDITIONS[$this->condition] ?? $this->condition;
    }
}
