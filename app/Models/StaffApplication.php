<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'staff_position_id',
        'status',
        'reviewer_notes',
        'conditions',
        'reviewed_by',
        'staff_review_thread_id',
        'interview_thread_id',
        'applicant_age',
        'applicant_member_since',
        'applicant_membership_level',
        'applicant_membership_level_since',
        'applicant_report_count',
        'applicant_commendation_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'applicant_member_since' => 'date',
            'applicant_membership_level_since' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function staffPosition(): BelongsTo
    {
        return $this->belongsTo(StaffPosition::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(StaffApplicationAnswer::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(StaffApplicationNote::class);
    }

    public function staffReviewThread(): BelongsTo
    {
        return $this->belongsTo(Thread::class, 'staff_review_thread_id');
    }

    public function interviewThread(): BelongsTo
    {
        return $this->belongsTo(Thread::class, 'interview_thread_id');
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function isPending(): bool
    {
        return ! $this->isTerminal();
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [
            ApplicationStatus::Submitted,
            ApplicationStatus::UnderReview,
            ApplicationStatus::Interview,
            ApplicationStatus::BackgroundCheck,
        ]);
    }

    public function scopeForPosition($query, int $positionId)
    {
        return $query->where('staff_position_id', $positionId);
    }
}
