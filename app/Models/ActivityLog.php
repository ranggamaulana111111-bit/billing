<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLog extends Model
{
    use BelongsToUser;

    protected $fillable = ['user_id', 'action', 'details'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function log(string $action, ?string $details = null): self
    {
        return static::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'details' => $details,
        ]);
    }
}
