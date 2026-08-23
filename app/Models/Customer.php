<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Services\Billing\CustomerCodeGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'customer_code', 'type', 'name', 'location', 'phone', 'email', 'nik', 'ktp_photo', 'package_id',
        'odp_point_id', 'odp_id', 'odp_port_id',
        'pppoe_username', 'pppoe_password', 'serial_number', 'modem_sn', 'modem_photo', 'mac_address', 'original_ppp_profile', 'due_date', 'status', 'suspended_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Customer $customer) {
            if (empty($customer->customer_code)) {
                $customer->customer_code = app(CustomerCodeGenerator::class)->generate();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'customer_code';
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function odp()
    {
        return $this->belongsTo(Odp::class, 'odp_id');
    }

    public function odpPort()
    {
        return $this->belongsTo(OdpPort::class, 'odp_port_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function onus()
    {
        return $this->hasMany(Onu::class);
    }

    public function mikrotikOnus()
    {
        return $this->hasMany(Onu::class)->where('onu_id', 'like', 'mikrotik-%');
    }

    public function getActiveInvoiceAttribute(): ?Invoice
    {
        return $this->invoices()->where('payment_status', 'unpaid')->latest()->first();
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->customer_code.' - '.$this->name;
    }

    public function scopePpp($query)
    {
        return $query->where('type', 'ppp');
    }

    public function scopeHotspot($query)
    {
        return $query->where('type', 'hotspot');
    }
}
