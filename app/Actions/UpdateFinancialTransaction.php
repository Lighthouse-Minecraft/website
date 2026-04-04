<?php

namespace App\Actions;

use App\Models\FinancialTransaction;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateFinancialTransaction
{
    use AsAction;

    public function handle(
        FinancialTransaction $transaction,
        int $accountId,
        string $type,
        int $amount,
        string $transactedAt,
        ?int $categoryId,
        ?string $notes,
        array $tagIds = []
    ): void {
        if ($transaction->isInPublishedMonth()) {
            throw new \RuntimeException('Cannot edit a transaction in a published month.');
        }

        $transaction->update([
            'account_id' => $accountId,
            'type' => $type,
            'amount' => $amount,
            'transacted_at' => $transactedAt,
            'financial_category_id' => $categoryId,
            'notes' => $notes ?: null,
        ]);

        $transaction->tags()->sync($tagIds);
    }
}
