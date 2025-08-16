<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'assigned_meeting_id', 'section_key', 'status', 'created_by', 'completed_by', 'completed_at', 'completed_meeting_id', 'archived_at', 'archived_meeting_id'];

    protected $casts = [
        'status' => TaskStatus::class,
        'completed_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function completedMeeting()
    {
        return $this->belongsTo(Meeting::class, 'completed_meeting_id');
    }

    public function assignedMeeting()
    {
        return $this->belongsTo(Meeting::class, 'assigned_meeting_id');
    }
}
