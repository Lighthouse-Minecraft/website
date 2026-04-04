<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class FinancialTransactionTag extends Pivot
{
    protected $table = 'financial_transaction_tags';

    public $incrementing = true;

    protected $fillable = [
        'financial_transaction_id',
        'financial_tag_id',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'financial_transaction_id');
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(FinancialTag::class, 'financial_tag_id');
    }
}
