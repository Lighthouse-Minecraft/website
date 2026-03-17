<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffApplicationAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_application_id',
        'application_question_id',
        'answer',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(StaffApplication::class, 'staff_application_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ApplicationQuestion::class, 'application_question_id');
    }
}
