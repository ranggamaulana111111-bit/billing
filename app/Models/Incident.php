<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'title', 'description', 'type', 'source', 'severity', 'status',
        'odp_id', 'odc_id', 'assigned_to', 'created_by',
        'detected_at', 'acknowledged_at', 'resolved_at',
        'sla_deadline', 'sla_status', 'notifiable_customer_ids',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'sla_deadline' => 'datetime',
        'notifiable_customer_ids' => 'array',
    ];

    public function odp()
    {
        return $this->belongsTo(Odp::class);
    }

    public function odc()
    {
        return $this->belongsTo(Odc::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function notifications()
    {
        return $this->hasMany(IncidentNotification::class);
    }

    public function affectedCustomers()
    {
        if (! $this->odp_id) {
            return Customer::whereRaw('1 = 0');
        }

        return Customer::where('odp_id', $this->odp_id);
    }

    public function getSlaRemainingAttribute(): ?string
    {
        if (! $this->sla_deadline || $this->status === 'resolved' || $this->status === 'closed') {
            return null;
        }

        $remaining = now()->diff($this->sla_deadline);

        if (now()->gt($this->sla_deadline)) {
            return 'Telat '.$remaining->h.'j '.$remaining->i.'m';
        }

        return $remaining->h.'j '.$remaining->i.'m lagi';
    }

    public function getSlaProgressAttribute(): int
    {
        if (! $this->sla_deadline || ! $this->detected_at) {
            return 0;
        }

        $total = $this->detected_at->diffInSeconds($this->sla_deadline);
        $elapsed = $this->detected_at->diffInSeconds(now());

        if ($total <= 0) {
            return 100;
        }

        return min(100, (int) (($elapsed / $total) * 100));
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['open', 'investigating']);
    }

    public function scopeBreached($query)
    {
        return $query->where('sla_status', 'breached');
    }

    public static function slaHoursForSeverity(string $severity): int
    {
        return match ($severity) {
            'critical' => 4,
            'high' => 8,
            'medium' => 24,
            'low' => 72,
            default => 24,
        };
    }
}
