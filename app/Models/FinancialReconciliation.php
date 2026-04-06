<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialReconciliation extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'period_id',
        'statement_date',
        'statement_ending_balance',
        'status',
        'completed_at',
        'completed_by_id',
    ];

    protected $casts = [
        'statement_date' => 'date',
        'statement_ending_balance' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'account_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'period_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FinancialReconciliationLine::class, 'reconciliation_id');
    }
}
