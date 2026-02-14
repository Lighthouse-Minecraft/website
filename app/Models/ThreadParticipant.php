<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThreadParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'thread_id',
        'user_id',
        'last_read_at',
        'is_viewer',
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
        'is_viewer' => 'boolean',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
