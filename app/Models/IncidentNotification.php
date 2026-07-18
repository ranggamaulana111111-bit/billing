<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentNotification extends Model
{
    protected $fillable = [
        'incident_id', 'recipient_phone', 'recipient_type', 'recipient_name',
        'customer_id', 'message', 'notification_type', 'status', 'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function incident()
    {
        return $this->belongsTo(Incident::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function markSent(): void
    {
        $this->update(['status' => 'sent', 'sent_at' => now()]);
    }

    public function markFailed(): void
    {
        $this->update(['status' => 'failed']);
    }
}
