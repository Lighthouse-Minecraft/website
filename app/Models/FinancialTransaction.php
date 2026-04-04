<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FinancialTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'type',
        'amount',
        'transacted_at',
        'financial_category_id',
        'target_account_id',
        'notes',
        'entered_by',
        'external_reference',
    ];

    protected $casts = [
        'amount' => 'integer',
        'transacted_at' => 'date',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'financial_category_id');
    }

    public function targetAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'target_account_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            FinancialTag::class,
            'financial_transaction_tags',
            'financial_transaction_id',
            'financial_tag_id'
        )->withTimestamps();
    }
}
