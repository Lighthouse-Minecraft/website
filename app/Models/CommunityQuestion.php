<?php

namespace App\Models;

use App\Enums\CommunityQuestionStatus;
use App\Enums\CommunityResponseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_text',
        'description',
        'status',
        'start_date',
        'end_date',
        'created_by',
        'suggested_by',
        'suggestion_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommunityQuestionStatus::class,
            'start_date' => 'datetime',
            'end_date' => 'datetime',
        ];
    }

    // Relationships

    public function responses(): HasMany
    {
        return $this->hasMany(CommunityResponse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function suggester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suggested_by');
    }

    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(QuestionSuggestion::class);
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CommunityQuestionStatus::Active);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', CommunityQuestionStatus::Archived);
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', CommunityQuestionStatus::Scheduled);
    }

    // Helpers

    public function approvedResponses(): HasMany
    {
        return $this->responses()->where('status', CommunityResponseStatus::Approved);
    }

    public function isActive(): bool
    {
        return $this->status === CommunityQuestionStatus::Active;
    }

    public function isArchived(): bool
    {
        return $this->status === CommunityQuestionStatus::Archived;
    }

    public function isDraft(): bool
    {
        return $this->status === CommunityQuestionStatus::Draft;
    }

    public function isScheduled(): bool
    {
        return $this->status === CommunityQuestionStatus::Scheduled;
    }
}
