<?php

namespace App\Actions;

use App\Models\FinancialJournalEntry;
use App\Models\FinancialPeriod;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateJournalEntry
{
    use AsAction;

    /**
     * Create a simple journal entry (income, expense, or transfer) with auto-generated
     * double-entry debit/credit lines.
     *
     * Income:   Debit $bankAccountId  / Credit $primaryAccountId (revenue account)
     * Expense:  Debit $primaryAccountId (expense account) / Credit $bankAccountId
     * Transfer: Debit $bankAccountId (to-account) / Credit $primaryAccountId (from-account)
     *
     * @param  User  $user  The user creating the entry.
     * @param  string  $type  income|expense|transfer
     * @param  int  $periodId  FinancialPeriod ID the entry belongs to.
     * @param  string  $date  Entry date (Y-m-d).
     * @param  string  $description  Human-readable description.
     * @param  int  $amountCents  Amount in integer cents.
     * @param  int  $primaryAccountId  Revenue/expense account (income/expense) or from-account (transfer).
     * @param  int  $bankAccountId  Bank/payment account or to-account (transfer).
     * @param  string  $status  draft|posted
     * @param  string|null  $donorEmail  Optional donor email (income only).
     * @param  int|null  $vendorId  Optional vendor (expense/transfer).
     * @param  int|null  $restrictedFundId  Optional restricted fund designation.
     * @param  int[]  $tagIds  Tag IDs to attach.
     * @param  string|null  $reference  Optional reference string.
     */
    public function handle(
        User $user,
        string $type,
        int $periodId,
        string $date,
        string $description,
        int $amountCents,
        int $primaryAccountId,
        int $bankAccountId,
        string $status = 'draft',
        ?string $donorEmail = null,
        ?int $vendorId = null,
        ?int $restrictedFundId = null,
        array $tagIds = [],
        ?string $reference = null,
    ): FinancialJournalEntry {
        $period = FinancialPeriod::findOrFail($periodId);

        if ($period->status === 'closed') {
            throw new \RuntimeException('Cannot create journal entries for a closed period.');
        }

        [$debitAccountId, $creditAccountId] = match ($type) {
            'income' => [$bankAccountId, $primaryAccountId],
            'expense' => [$primaryAccountId, $bankAccountId],
            'transfer' => [$bankAccountId, $primaryAccountId],
            default => throw new \InvalidArgumentException("Unknown entry type: {$type}"),
        };

        $entry = FinancialJournalEntry::create([
            'period_id' => $periodId,
            'date' => $date,
            'description' => $description,
            'reference' => $reference,
            'entry_type' => $type,
            'status' => $status,
            'posted_at' => $status === 'posted' ? now() : null,
            'posted_by_id' => $status === 'posted' ? $user->id : null,
            'donor_email' => $donorEmail,
            'vendor_id' => $vendorId,
            'restricted_fund_id' => $restrictedFundId,
            'created_by_id' => $user->id,
        ]);

        $entry->lines()->create([
            'account_id' => $debitAccountId,
            'debit' => $amountCents,
            'credit' => 0,
        ]);

        $entry->lines()->create([
            'account_id' => $creditAccountId,
            'debit' => 0,
            'credit' => $amountCents,
        ]);

        if ($tagIds) {
            $entry->tags()->sync($tagIds);
        }

        return $entry;
    }
}
