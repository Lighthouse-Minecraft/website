<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MinecraftCommandLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'command',
        'command_type',
        'target',
        'status',
        'response',
        'error_message',
        'ip_address',
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

    public function user()
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
