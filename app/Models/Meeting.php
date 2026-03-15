<?php

namespace App\Models;

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Enums\StaffRank;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class Meeting extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'type', 'day', 'scheduled_time', 'is_public', 'agenda', 'minutes', 'community_minutes', 'show_community_updates'];

    protected function casts(): array
    {
        return [
            'day' => 'string',
            'scheduled_time' => 'datetime',
            'is_public' => 'boolean',
            'show_community_updates' => 'boolean',
            'type' => MeetingType::class,
            'status' => MeetingStatus::class,
            'start_time' => 'datetime',
            'end_time' => 'datetime',
        ];
    }

    public function startMeeting(): void
    {
        if ($this->status !== MeetingStatus::Pending) {
            throw new \Exception('Meeting cannot be started unless it is pending.');
        }

        $this->status = MeetingStatus::InProgress;
        $this->start_time = now();
        $this->save();

        // Seed attendance records for all active staff
        $staffUserIds = User::where('staff_rank', '>=', StaffRank::JrCrew->value)
            ->pluck('id');

        $now = now();
        $starterId = Auth::id();
        $records = [];
        foreach ($staffUserIds as $userId) {
            $records[$userId] = [
                'added_at' => $now,
                'attended' => $userId === $starterId,
            ];
        }

        $this->attendees()->syncWithoutDetaching($records);
    }

    public function endMeeting(): void
    {
        if ($this->status !== MeetingStatus::InProgress) {
            throw new \Exception('Meeting cannot be ended unless it is in progress.');
        }

        $this->status = MeetingStatus::Finalizing;
        $this->end_time = now();
        $this->save();
    }

    public function completeMeeting(): void
    {
        if ($this->status !== MeetingStatus::Finalizing) {
            throw new \Exception('Meeting cannot be completed unless it is finalizing.');
        }

        $this->status = MeetingStatus::Completed;
        $this->save();

        Cache::forget('command_dashboard.iteration_boundaries');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(MeetingNote::class);
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('added_at', 'attended')
            ->withTimestamps()
            ->orderBy('meeting_user.added_at');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(MeetingQuestion::class)->orderBy('sort_order');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(MeetingReport::class);
    }

    public function archivedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'archived_meeting_id');
    }

    public function isStaffMeeting(): bool
    {
        return $this->type === MeetingType::StaffMeeting;
    }

    public function isReportUnlocked(): bool
    {
        if (! $this->isStaffMeeting()) {
            return false;
        }

        if ($this->isReportLocked()) {
            return false;
        }

        $unlockDays = config('lighthouse.meeting_report_unlock_days', 7);

        return now()->gte($this->scheduled_time->copy()->subDays($unlockDays));
    }

    public function isReportLocked(): bool
    {
        return in_array($this->status, [
            MeetingStatus::InProgress,
            MeetingStatus::Finalizing,
            MeetingStatus::Completed,
            MeetingStatus::Archived,
            MeetingStatus::Cancelled,
        ]);
    }
}
