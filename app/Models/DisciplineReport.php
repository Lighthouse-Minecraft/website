<?php

namespace App\Models;

use App\Enums\ReportLocation;
use App\Enums\ReportSeverity;
use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DisciplineReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_user_id',
        'reporter_user_id',
        'publisher_user_id',
        'report_category_id',
        'description',
        'location',
        'witnesses',
        'actions_taken',
        'severity',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'location' => ReportLocation::class,
            'severity' => ReportSeverity::class,
            'status' => ReportStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publisher_user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ReportCategory::class, 'report_category_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ReportStatus::Published);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', ReportStatus::Draft);
    }

    public function scopeForSubject(Builder $query, User $user): Builder
    {
        return $query->where('subject_user_id', $user->id);
    }

    public function isDraft(): bool
    {
        return $this->status === ReportStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status === ReportStatus::Published;
    }

    public function violatedRules(): BelongsToMany
    {
        return $this->belongsToMany(Rule::class, 'discipline_report_rules');
    }

    public function topics(): MorphMany
    {
        return $this->morphMany(Thread::class, 'topicable');
    }
}
