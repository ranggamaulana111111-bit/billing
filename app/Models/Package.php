<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'name', 'speed', 'description', 'price', 'billing_cycle', 'mikrotik_profile', 'is_active', 'download_mbps', 'upload_mbps'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'download_mbps' => 'integer',
            'upload_mbps' => 'integer',
        ];
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function getDownloadRate(): string
    {
        return ($this->download_mbps ?? (int) ($this->speed ?? 0)).'M';
    }

    public function getUploadRate(): string
    {
        return ($this->upload_mbps ?? (int) ($this->speed ?? 0)).'M';
    }

    public function hasQosConfig(): bool
    {
        $dl = $this->download_mbps ?? (int) ($this->speed ?? 0);
        $ul = $this->upload_mbps ?? (int) ($this->speed ?? 0);

        return $dl > 0 && $ul > 0;
    }
}
