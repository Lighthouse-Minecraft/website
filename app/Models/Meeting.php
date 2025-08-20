<?php

namespace App\Models;

use App\Enums\MeetingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Meeting extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'day', 'scheduled_time', 'is_public', 'agenda', 'minutes', 'community_minutes'];

    protected $casts = [
        'day' => 'string',
        'scheduled_time' => 'datetime',
        'is_public' => 'boolean',
        'status' => MeetingStatus::class,
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function startMeeting(): void
    {
        if ($this->status !== MeetingStatus::Pending) {
            throw new \Exception('Meeting cannot be started unless it is pending.');
        }

        $this->status = MeetingStatus::InProgress;
        $this->start_time = now();
        $this->save();

        // Auto-add the person who starts the meeting as an attendee
        if (Auth::check()) {
            $this->attendees()->syncWithoutDetaching([
                Auth::id() => ['added_at' => now()],
            ]);
        }
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
    }

    public function notes(): HasMany
    {
        return $this->hasMany(MeetingNote::class);
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('added_at')
            ->withTimestamps()
            ->orderBy('pivot_added_at');
    }
}
