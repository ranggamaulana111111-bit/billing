<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use BelongsToUser;

    protected $fillable = ['user_id', 'invoice_id', 'amount', 'payment_method', 'payment_date', 'notes'];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
