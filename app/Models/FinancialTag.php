<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FinancialTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'color',
    ];

    public function journalEntries(): BelongsToMany
    {
        return $this->belongsToMany(FinancialJournalEntry::class, 'financial_journal_entry_tags', 'tag_id', 'journal_entry_id');
    }
}
