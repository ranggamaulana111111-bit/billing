<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MikrotikRouter extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'identity', 'host', 'local_ip', 'local_port', 'connection_mode', 'port', 'ssh_port', 'api_ssl_port',
        'username', 'password', 'hotspot_server', 'type',
        'routeros_version', 'model', 'architecture', 'serial_number',
        'site', 'location', 'timezone', 'latitude', 'longitude',
        'management_vlan', 'management_interface', 'connection_type', 'status',
        'last_seen', 'last_connected', 'timeout', 'notes', 'tags', 'is_active',
        'user_stats', 'user_stats_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'ssh_port' => 'integer',
            'local_port' => 'integer',
            'api_ssl_port' => 'integer',
            'management_vlan' => 'integer',
            'timeout' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'last_seen' => 'datetime',
            'last_connected' => 'datetime',
            'tags' => 'array',
            'password' => 'encrypted',
            'user_stats' => 'array',
            'user_stats_updated_at' => 'datetime',
        ];
    }

    public function scopeByConnectionMode($query, string $mode)
    {
        return $query->where('connection_mode', $mode);
    }

    public function scopeByType($query, string $type)
    {
        if ($type === 'general') {
            return $query;
        }

        return $query->where(function ($q) use ($type) {
            $q->where('type', $type)->orWhere('type', 'general');
        });
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (! $search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('host', 'like', "%{$search}%")
                ->orWhere('identity', 'like', "%{$search}%")
                ->orWhere('site', 'like', "%{$search}%")
                ->orWhere('location', 'like', "%{$search}%")
                ->orWhere('model', 'like', "%{$search}%")
                ->orWhere('serial_number', 'like', "%{$search}%");
        });
    }

    public function scopeByTags($query, ?array $tags)
    {
        if (! $tags) {
            return $query;
        }

        return $query->where(function ($q) use ($tags) {
            foreach ($tags as $tag) {
                $q->orWhereJsonContains('tags', $tag);
            }
        });
    }

    public function getDisplayIdentityAttribute(): string
    {
        return $this->identity ?: $this->name;
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'online' => 'success',
            'offline' => 'danger',
            'degraded' => 'warning',
            default => 'secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'online' => 'Online',
            'offline' => 'Offline',
            'degraded' => 'Degraded',
            default => 'Unknown',
        };
    }

    /**
     * Candidate hosts untuk koneksi, urut dari utama (host) ke fallback (local_ip).
     */
    public function getConnectionHosts(): array
    {
        return array_values(array_filter(array_unique([
            $this->host,
            $this->local_ip,
        ])));
    }

    public function hasLocalIpFallback(): bool
    {
        return filled($this->local_ip) && $this->local_ip !== $this->host;
    }

    public function vouchers()
    {
        return $this->hasMany(Voucher::class, 'router_id');
    }
}
