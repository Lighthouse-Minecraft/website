<?php

namespace Database\Factories;

use App\Models\FinancialAccount;
use App\Models\FinancialPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinancialBudgetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'account_id' => FinancialAccount::factory(),
            'period_id' => FinancialPeriod::factory(),
            'amount' => fake()->numberBetween(1000, 100000),
        ];
    }
}
