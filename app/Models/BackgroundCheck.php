<?php

namespace App\Models;

use App\Enums\BackgroundCheckStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BackgroundCheck extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'run_by_user_id',
        'service',
        'completed_date',
        'status',
        'notes',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_date' => 'date',
            'status' => BackgroundCheckStatus::class,
            'locked_at' => 'datetime',
        ];
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function runByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'run_by_user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BackgroundCheckDocument::class);
    }
}
