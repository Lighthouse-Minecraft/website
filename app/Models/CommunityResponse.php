<?php

namespace App\Models;

use App\Enums\CommunityResponseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'community_question_id',
        'user_id',
        'body',
        'image_path',
        'status',
        'reviewed_by',
        'reviewed_at',
        'approved_at',
        'featured_in_blog_url',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommunityResponseStatus::class,
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    // Relationships

    public function question(): BelongsTo
    {
        return $this->belongsTo(CommunityQuestion::class, 'community_question_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(CommunityReaction::class);
    }

    // Scopes

    public function scopeApproved($query)
    {
        return $query->where('status', CommunityResponseStatus::Approved);
    }

    public function scopePendingReview($query)
    {
        return $query->whereIn('status', [
            CommunityResponseStatus::Submitted,
            CommunityResponseStatus::UnderReview,
        ]);
    }

    // Helpers

    public function isApproved(): bool
    {
        return $this->status === CommunityResponseStatus::Approved;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [
            CommunityResponseStatus::Submitted,
            CommunityResponseStatus::UnderReview,
        ]);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path
            ? \App\Services\StorageService::publicUrl($this->image_path)
            : null;
    }
}
