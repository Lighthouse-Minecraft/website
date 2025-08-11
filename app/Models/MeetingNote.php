<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'section_key',
        'meeting_id',
        'content',
        'locked_by',
        'locked_at',
        'lock_updated_at',
    ];

    protected $with = ['lockedBy', 'createdBy'];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
