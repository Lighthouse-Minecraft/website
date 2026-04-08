<?php

namespace Database\Factories;

use App\Models\FinancialAccount;
use App\Models\FinancialJournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinancialJournalEntryLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'journal_entry_id' => FinancialJournalEntry::factory(),
            'account_id' => FinancialAccount::factory(),
            'debit' => fake()->numberBetween(0, 10000),
            'credit' => 0,
            'memo' => null,
        ];
    }

    public function debit(int $amount): static
    {
        return $this->state(['debit' => $amount, 'credit' => 0]);
    }

    public function credit(int $amount): static
    {
        return $this->state(['debit' => 0, 'credit' => $amount]);
    }
}
