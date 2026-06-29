<?php

namespace App\Models\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant_id', function (Builder $builder) {
            if (Auth::hasUser()) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', Auth::user()->tenant_id);
            }
        });

        static::creating(function ($model) {
            if (Auth::hasUser() && ! $model->tenant_id) {
                $model->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where($query->getModel()->getTable().'.tenant_id', $tenantId);
    }

    public function scopeAllTenants(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant_id');
    }
}
