<?php

namespace App\Actions;

use App\Models\FinancialAccount;
use Lorisleiva\Actions\Concerns\AsAction;

class ArchiveFinancialAccount
{
    use AsAction;

    public function handle(FinancialAccount $account): void
    {
        $account->is_archived = true;
        $account->save();
    }
}
