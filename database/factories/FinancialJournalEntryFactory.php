<?php

namespace Database\Factories;

use App\Models\FinancialPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinancialJournalEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'period_id' => FinancialPeriod::factory(),
            'date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'description' => fake()->sentence(),
            'entry_type' => fake()->randomElement(['income', 'expense', 'transfer']),
            'status' => 'draft',
            'created_by_id' => User::factory(),
        ];
    }

    public function posted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'posted',
            'posted_at' => now(),
            'posted_by_id' => User::factory(),
        ]);
    }

    public function income(): static
    {
        return $this->state(['entry_type' => 'income']);
    }

    public function expense(): static
    {
        return $this->state(['entry_type' => 'expense']);
    }

    public function transfer(): static
    {
        return $this->state(['entry_type' => 'transfer']);
    }
}
