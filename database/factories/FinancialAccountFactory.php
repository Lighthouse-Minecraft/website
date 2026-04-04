<?php

namespace Database\Factories;

use App\Models\FinancialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FinancialAccount>
 */
class FinancialAccountFactory extends Factory
{
    protected $model = FinancialAccount::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'type' => $this->faker->randomElement(['checking', 'savings', 'payment-processor', 'cash']),
            'opening_balance' => 0,
            'is_archived' => false,
        ];
    }

    public function checking(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'checking',
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_archived' => true,
        ]);
    }

    public function withOpeningBalance(int $cents): static
    {
        return $this->state(fn (array $attributes) => [
            'opening_balance' => $cents,
        ]);
    }
}
