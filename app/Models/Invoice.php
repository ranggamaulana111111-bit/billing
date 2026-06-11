<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory, BelongsToUser;

    protected $fillable = ['user_id', 'invoice_code', 'customer_id', 'amount', 'payment_status', 'paid_at', 'payment_method', 'midtrans_order_id'];

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
}
