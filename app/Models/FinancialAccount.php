<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'opening_balance',
        'is_archived',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
        'opening_balance' => 'integer',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'account_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'target_account_id');
    }

    public function currentBalance(): int
    {
        $credits = (int) $this->transactions()
            ->where('type', 'income')
            ->sum('amount');

        $debits = (int) $this->transactions()
            ->where('type', 'expense')
            ->sum('amount');

        $transfersOut = (int) $this->transactions()
            ->where('type', 'transfer')
            ->sum('amount');

        $transfersIn = (int) $this->incomingTransfers()
            ->where('type', 'transfer')
            ->sum('amount');

        return $this->opening_balance + $credits - $debits - $transfersOut + $transfersIn;
    }
}
