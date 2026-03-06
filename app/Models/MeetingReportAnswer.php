<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingReportAnswer extends Model
{
    protected $fillable = [
        'meeting_report_id',
        'meeting_question_id',
        'answer',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(MeetingReport::class, 'meeting_report_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(MeetingQuestion::class, 'meeting_question_id');
    }
}
