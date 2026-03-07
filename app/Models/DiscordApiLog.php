<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscordApiLog extends Model
{
    protected $fillable = [
        'user_id',
        'method',
        'endpoint',
        'action_type',
        'target',
        'status',
        'http_status',
        'response',
        'error_message',
        'meta',
        'executed_at',
        'execution_time_ms',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'executed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
