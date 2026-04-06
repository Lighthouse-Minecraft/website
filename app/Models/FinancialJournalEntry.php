<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FinancialJournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_id',
        'date',
        'description',
        'reference',
        'entry_type',
        'status',
        'posted_at',
        'posted_by_id',
        'reverses_entry_id',
        'donor_email',
        'vendor_id',
        'restricted_fund_id',
        'created_by_id',
    ];

    protected $casts = [
        'date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'period_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_id');
    }

    public function reversesEntry(): BelongsTo
    {
        return $this->belongsTo(FinancialJournalEntry::class, 'reverses_entry_id');
    }

    public function reversedBy(): HasOne
    {
        return $this->hasOne(FinancialJournalEntry::class, 'reverses_entry_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(FinancialVendor::class, 'vendor_id');
    }

    public function restrictedFund(): BelongsTo
    {
        return $this->belongsTo(FinancialRestrictedFund::class, 'restricted_fund_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FinancialJournalEntryLine::class, 'journal_entry_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(FinancialTag::class, 'financial_journal_entry_tags', 'journal_entry_id', 'tag_id');
    }
}
