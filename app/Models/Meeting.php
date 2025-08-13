<?php

namespace App\Models;

use App\Enums\MeetingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meeting extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'day', 'scheduled_time', 'is_public'];

    protected $casts = [
        'day' => 'string',
        'scheduled_time' => 'datetime',
        'is_public' => 'boolean',
        'status' => MeetingStatus::class,
    ];

    public function startMeeting(): void
    {
        if ($this->status !== MeetingStatus::Pending) {
            throw new \Exception('Meeting cannot be started unless it is pending.');
        }

        $this->status = MeetingStatus::InProgress;
        $this->start_time = now();
        $this->save();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(MeetingNote::class);
    }
}
