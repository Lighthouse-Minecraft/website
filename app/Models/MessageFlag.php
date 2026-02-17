<?php

namespace App\Models;

use App\Enums\MessageFlagStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageFlag extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'thread_id',
        'flagged_by_user_id',
        'note',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'staff_notes',
        'flag_review_ticket_id',
    ];

    protected $casts = [
        'status' => MessageFlagStatus::class,
        'reviewed_at' => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function flaggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'flagged_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function flagReviewTicket(): BelongsTo
    {
        return $this->belongsTo(Thread::class, 'flag_review_ticket_id');
    }
}
