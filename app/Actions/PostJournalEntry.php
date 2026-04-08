<?php

namespace App\Actions;

use App\Models\FinancialJournalEntry;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class PostJournalEntry
{
    use AsAction;

    /**
     * Post a draft journal entry, making it permanently immutable.
     *
     * @throws \RuntimeException if the entry is already posted, the period is closed,
     *                           or the entry is unbalanced.
     */
    public function handle(User $user, FinancialJournalEntry $entry): void
    {
        if ($entry->status === 'posted') {
            throw new \RuntimeException('This entry is already posted.');
        }

        if ($entry->period->status === 'closed') {
            throw new \RuntimeException('Cannot post to a closed period.');
        }

        $totalDebit = $entry->lines->sum('debit');
        $totalCredit = $entry->lines->sum('credit');

        if ($totalDebit !== $totalCredit) {
            throw new \RuntimeException('Journal entry is unbalanced: debits do not equal credits.');
        }

        $entry->update([
            'status' => 'posted',
            'posted_at' => now(),
            'posted_by_id' => $user->id,
        ]);
    }
}
