<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'author_id',
        'is_published',
        'published_at',
        'expired_at',
        'notifications_sent_at',
    ];

    protected $table = 'announcements';

    protected $with = ['author'];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'expired_at' => 'datetime',
        'notifications_sent_at' => 'datetime',
    ];

    // -------------------- Relationships --------------------

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function acknowledgers()
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    // -------------------- Scopes --------------------

    /**
     * Published, not future-scheduled, and not expired.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expired_at')
                    ->orWhere('expired_at', '>', now());
            });
    }

    /**
     * Expired: is_published but expired_at is in the past.
     */
    public function scopeExpired($query)
    {
        return $query->where('is_published', true)
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', now());
    }

    // -------------------- Helpers --------------------

    public function isExpired(): bool
    {
        return $this->expired_at !== null && $this->expired_at->isPast();
    }

    public function isAuthoredBy(User $user): bool
    {
        return $this->author_id === $user->id;
    }

    public function authorName(): string
    {
        return $this->author ? $this->author->name : 'Unknown Author';
    }
}
