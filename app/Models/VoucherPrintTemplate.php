<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class VoucherPrintTemplate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'paper_size', 'content', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function active(): ?self
    {
        return static::where('is_active', true)->latest()->first();
    }

    public static function defaultContent(): string
    {
        return <<<'HTML'
<div class="voucher">
    <div class="v-header">{COMPANY}</div>
    <div class="v-title">VOUCHER WIFI</div>
    <div class="v-row"><span>User</span><b>{USERNAME}</b></div>
    <div class="v-row"><span>Pass</span><b>{PASSWORD}</b></div>
    <div class="v-row"><span>Masa Aktif</span><b>{DURATION}</b></div>
    <div class="v-row"><span>Server</span><b>{HOTSPOT_SERVER}</b></div>
    <div class="v-footer">Hubungi {ADMIN_PHONE} untuk bantuan</div>
</div>
HTML;
    }
}
