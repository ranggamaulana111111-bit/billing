<?php

namespace App\Models\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope('user_id', function (Builder $builder) {
            if (Auth::hasUser()) {
                $builder->where($builder->getModel()->getTable().'.user_id', Auth::id());
            }
        });

        static::creating(function ($model) {
            if (Auth::hasUser()) {
                $model->user_id = Auth::id();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where($query->getModel()->getTable().'.user_id', $userId);
    }

    public function scopeAllUsers(Builder $query): Builder
    {
        return $query->withoutGlobalScope('user_id');
    }
}
