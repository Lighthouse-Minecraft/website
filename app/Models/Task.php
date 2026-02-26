<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'assigned_meeting_id', 'section_key', 'status', 'created_by', 'completed_by', 'completed_at', 'completed_meeting_id', 'archived_at', 'archived_meeting_id', 'assigned_to_user_id'];

    protected $casts = [
        'status' => TaskStatus::class,
        'completed_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function completedMeeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'completed_meeting_id');
    }

    public function assignedMeeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'assigned_meeting_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}
