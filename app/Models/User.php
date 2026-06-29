<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'provider',
        'provider_id',
        'avatar',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'tenant_id', 'tenant_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'tenant_id', 'tenant_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'tenant_id', 'tenant_id');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class, 'tenant_id', 'tenant_id');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class, 'tenant_id', 'tenant_id');
    }

    public function odcs(): HasMany
    {
        return $this->hasMany(Odc::class, 'tenant_id', 'tenant_id');
    }

    public function odpRoutes(): HasMany
    {
        return $this->hasMany(OdpRoute::class, 'tenant_id', 'tenant_id');
    }

    public function odpPoints(): HasMany
    {
        return $this->hasMany(OdpPoint::class, 'tenant_id', 'tenant_id');
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class, 'tenant_id', 'tenant_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'tenant_id', 'tenant_id');
    }
}
