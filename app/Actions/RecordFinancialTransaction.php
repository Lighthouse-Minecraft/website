<?php

namespace App\Actions;

use App\Models\FinancialTransaction;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class RecordFinancialTransaction
{
    use AsAction;

    public function handle(
        User $enteredBy,
        int $accountId,
        string $type,
        int $amount,
        string $transactedAt,
        ?int $categoryId,
        ?string $notes,
        array $tagIds = []
    ): FinancialTransaction {
        $transaction = FinancialTransaction::create([
            'account_id' => $accountId,
            'type' => $type,
            'amount' => $amount,
            'transacted_at' => $transactedAt,
            'financial_category_id' => $categoryId,
            'target_account_id' => null,
            'notes' => $notes ?: null,
            'entered_by' => $enteredBy->id,
            'external_reference' => null,
        ]);

        if (! empty($tagIds)) {
            $transaction->tags()->sync($tagIds);
        }

        return $transaction;
    }
}
