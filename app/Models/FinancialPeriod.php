<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'fiscal_year',
        'month_number',
        'name',
        'start_date',
        'end_date',
        'status',
        'closed_at',
        'closed_by_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_id');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(FinancialJournalEntry::class, 'period_id');
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(FinancialBudget::class, 'period_id');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(FinancialReconciliation::class, 'period_id');
    }
}
