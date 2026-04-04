<?php

namespace App\Actions;

use App\Models\FinancialAccount;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateFinancialAccount
{
    use AsAction;

    public function handle(string $name, string $type, int $openingBalance): FinancialAccount
    {
        return FinancialAccount::create([
            'name' => $name,
            'type' => $type,
            'opening_balance' => $openingBalance,
            'is_archived' => false,
        ]);
    }
}
