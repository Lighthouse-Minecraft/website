<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'subtype',
        'description',
        'normal_balance',
        'fund_type',
        'is_bank_account',
        'is_active',
    ];

    protected $casts = [
        'is_bank_account' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(FinancialJournalEntryLine::class, 'account_id');
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(FinancialBudget::class, 'account_id');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(FinancialReconciliation::class, 'account_id');
    }
}
