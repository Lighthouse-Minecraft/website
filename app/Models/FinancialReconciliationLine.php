<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialReconciliationLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'reconciliation_id',
        'journal_entry_line_id',
        'cleared_at',
    ];

    protected $casts = [
        'cleared_at' => 'datetime',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(FinancialReconciliation::class, 'reconciliation_id');
    }

    public function journalEntryLine(): BelongsTo
    {
        return $this->belongsTo(FinancialJournalEntryLine::class, 'journal_entry_line_id');
    }
}
