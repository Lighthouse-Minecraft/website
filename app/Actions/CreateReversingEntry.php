<?php

namespace App\Actions;

use App\Models\FinancialJournalEntry;
use App\Models\FinancialPeriod;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateReversingEntry
{
    use AsAction;

    /**
     * Create a reversing journal entry (all debit/credit lines inverted) against a posted entry.
     * The new entry is saved as a draft with `reverses_entry_id` referencing the original.
     *
     * @throws \RuntimeException if the entry is not posted or if the period is closed.
     */
    public function handle(User $user, FinancialJournalEntry $entry): FinancialJournalEntry
    {
        if ($entry->status !== 'posted') {
            throw new \RuntimeException('Can only reverse a posted entry.');
        }

        $period = FinancialPeriod::where('start_date', '<=', now()->toDateString())
            ->where('end_date', '>=', now()->toDateString())
            ->where('status', '!=', 'closed')
            ->first();

        if (! $period) {
            $period = $entry->period;
        }

        $reversing = FinancialJournalEntry::create([
            'period_id' => $period->id,
            'date' => now()->toDateString(),
            'description' => 'Reversing entry for: '.$entry->description,
            'entry_type' => $entry->entry_type,
            'status' => 'draft',
            'reverses_entry_id' => $entry->id,
            'created_by_id' => $user->id,
        ]);

        foreach ($entry->lines as $line) {
            $reversing->lines()->create([
                'account_id' => $line->account_id,
                'debit' => $line->credit,
                'credit' => $line->debit,
                'memo' => $line->memo,
            ]);
        }

        return $reversing;
    }
}
