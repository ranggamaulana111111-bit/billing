<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class IsolirTemplate extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'template', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getTemplateDataAttribute(): array
    {
        $decoded = json_decode($this->template, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function activate(): void
    {
        static::where('tenant_id', $this->tenant_id)
            ->where('id', '!=', $this->id)
            ->update(['is_active' => false]);

        $this->update(['is_active' => true]);
    }
}
