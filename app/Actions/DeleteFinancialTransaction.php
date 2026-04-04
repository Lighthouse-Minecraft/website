<?php

namespace App\Actions;

use App\Models\FinancialTransaction;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteFinancialTransaction
{
    use AsAction;

    public function handle(FinancialTransaction $transaction): void
    {
        if ($transaction->isInPublishedMonth()) {
            throw new \RuntimeException('Cannot delete a transaction in a published month.');
        }

        $transaction->tags()->detach();
        $transaction->delete();
    }
}
