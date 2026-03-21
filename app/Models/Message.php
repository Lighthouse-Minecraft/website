<?php

namespace App\Models;

use App\Enums\MessageKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'thread_id',
        'user_id',
        'body',
        'kind',
        'image_path',
        'image_was_purged',
        'is_pending_moderation',
        'deleted_by',
    ];

    protected $casts = [
        'kind' => MessageKind::class,
        'image_was_purged' => 'boolean',
        'is_pending_moderation' => 'boolean',
    ];

    public function imageUrl(): ?string
    {
        return $this->image_path
            ? \App\Services\StorageService::publicUrl($this->image_path)
            : null;
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function flags(): HasMany
    {
        return $this->hasMany(MessageFlag::class);
    }
}
