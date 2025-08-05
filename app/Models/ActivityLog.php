<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ActivityLog extends Model
{
    protected $fillable = [
        'causer_id',
        'subject_type',
        'subject_id',
        'action',
        'description',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /**
     * Get the user that caused the activity.
     */
    public function causer()
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    /**
     * Get the subject of the activity.
     */
    public function subject()
    {
        return $this->morphTo();
    }

    public function scopeRelevantTo(Builder $query, User $user): Builder
    {
        return $query->where('causer_id', $user->id)
            ->orWhere(function ($query) use ($user) {
                $query->where('subject_type', User::class)
                    ->where('subject_id', $user->id);
            });
    }
}
