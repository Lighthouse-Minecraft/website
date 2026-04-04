<?php

namespace App\Actions;

use App\Models\FinancialPeriodReport;
use App\Models\FinancialTransaction;
use Illuminate\Support\Carbon;
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
        ?int $targetAccountId = null,
        ?string $notes = null,
        array $tagIds = []
    ): void {
        if ($transaction->isInPublishedMonth()) {
            throw new \RuntimeException('Cannot edit a transaction in a published month.');
        }

        // Block moving the transaction date into a published month
        $targetMonthStart = Carbon::parse($transactedAt)->startOfMonth()->toDateString();
        $targetIsPublished = FinancialPeriodReport::whereDate('month', $targetMonthStart)
            ->whereNotNull('published_at')
            ->exists();

        if ($targetIsPublished) {
            throw new \RuntimeException('Cannot move a transaction into a published month.');
        }

        $transaction->update([
            'account_id' => $accountId,
            'type' => $type,
            'amount' => $amount,
            'transacted_at' => $transactedAt,
            'financial_category_id' => $type === 'transfer' ? null : $categoryId,
            'target_account_id' => $type === 'transfer' ? $targetAccountId : null,
            'notes' => $notes ?: null,
        ]);

        $transaction->tags()->sync($tagIds);
    }
}
