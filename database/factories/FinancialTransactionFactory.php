<?php

namespace Database\Factories;

use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FinancialTransaction>
 */
class FinancialTransactionFactory extends Factory
{
    protected $model = FinancialTransaction::class;

    public function definition(): array
    {
        return [
            'account_id' => FinancialAccount::factory(),
            'type' => 'income',
            'amount' => $this->faker->numberBetween(100, 100000),
            'transacted_at' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'financial_category_id' => null,
            'target_account_id' => null,
            'notes' => null,
            'entered_by' => User::factory(),
            'external_reference' => null,
        ];
    }

    public function income(int $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'income',
            'amount' => $amount,
        ]);
    }

    public function expense(int $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'expense',
            'amount' => $amount,
        ]);
    }

    public function transfer(FinancialAccount $targetAccount, int $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'transfer',
            'amount' => $amount,
            'target_account_id' => $targetAccount->id,
            'financial_category_id' => null,
        ]);
    }
}
