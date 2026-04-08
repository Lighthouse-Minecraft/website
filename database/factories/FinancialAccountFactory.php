<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class FinancialAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numberBetween(6000, 9999),
            'name' => fake()->words(3, true),
            'type' => fake()->randomElement(['asset', 'liability', 'net_assets', 'revenue', 'expense']),
            'subtype' => null,
            'description' => null,
            'normal_balance' => fake()->randomElement(['debit', 'credit']),
            'fund_type' => 'unrestricted',
            'is_bank_account' => false,
            'is_active' => true,
        ];
    }

    public function asset(): static
    {
        return $this->state(['type' => 'asset', 'normal_balance' => 'debit']);
    }

    public function revenue(): static
    {
        return $this->state(['type' => 'revenue', 'normal_balance' => 'credit']);
    }

    public function expense(): static
    {
        return $this->state(['type' => 'expense', 'normal_balance' => 'debit']);
    }

    public function bankAccount(): static
    {
        return $this->state(['type' => 'asset', 'normal_balance' => 'debit', 'is_bank_account' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
