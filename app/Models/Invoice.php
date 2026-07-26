<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = ['tenant_id', 'invoice_code', 'invoice_number', 'customer_id', 'amount', 'payment_status',
        'billing_period', 'period', 'status', 'paid_at', 'payment_method', 'midtrans_order_id',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getInvoiceDisplayAttribute(): string
    {
        return $this->invoice_number ?? $this->invoice_code;
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function getIsOverdueAttribute(): bool
    {
        if ($this->payment_status === 'paid') {
            return false;
        }

        $dueDay = $this->customer?->due_date ? (int) $this->customer->due_date->format('d') : null;

        return $dueDay !== null && now()->day > $dueDay;
    }

    public function getDaysUntilDueAttribute(): ?int
    {
        $dueDay = $this->customer?->due_date ? (int) $this->customer->due_date->format('d') : null;

        return $dueDay !== null ? $dueDay - (int) now()->format('d') : null;
    }
}
