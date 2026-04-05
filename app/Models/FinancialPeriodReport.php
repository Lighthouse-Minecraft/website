<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialPeriodReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'month',
        'published_at',
        'published_by',
        'summary_snapshot',
    ];

    protected $casts = [
        'month' => 'date:Y-m-d',
        'published_at' => 'datetime',
        'summary_snapshot' => 'array',
    ];

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
