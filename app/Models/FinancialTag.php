<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FinancialTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'created_by',
        'is_archived',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(
            FinancialTransaction::class,
            'financial_transaction_tags',
            'financial_tag_id',
            'financial_transaction_id'
        )->withTimestamps();
    }
}
