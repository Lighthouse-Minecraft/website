<?php

namespace App\Actions;

use App\Models\FinancialAccount;
use Lorisleiva\Actions\Concerns\AsAction;

class ArchiveFinancialAccount
{
    use AsAction;

    public function handle(FinancialAccount $account): void
    {
        if ($account->currentBalance() !== 0) {
            throw new \RuntimeException('Cannot archive an account with a non-zero balance. Please transfer or clear the balance first.');
        }

        $account->is_archived = true;
        $account->save();
    }
}
