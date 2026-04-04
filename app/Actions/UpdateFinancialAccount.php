<?php

namespace App\Actions;

use App\Models\FinancialAccount;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateFinancialAccount
{
    use AsAction;

    public function handle(FinancialAccount $account, string $name): void
    {
        $account->name = $name;
        $account->save();
    }
}
