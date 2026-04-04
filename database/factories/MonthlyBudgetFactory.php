<?php

namespace Database\Factories;

use App\Models\FinancialCategory;
use App\Models\MonthlyBudget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MonthlyBudget>
 */
class MonthlyBudgetFactory extends Factory
{
    protected $model = MonthlyBudget::class;

    public function definition(): array
    {
        return [
            'financial_category_id' => FinancialCategory::factory(),
            'month' => now()->startOfMonth()->toDateString(),
            'planned_amount' => $this->faker->numberBetween(1000, 100000),
        ];
    }

    public function forMonth(string $month): static
    {
        return $this->state(fn (array $attributes) => [
            'month' => $month,
        ]);
    }

    public function forCategory(int $categoryId): static
    {
        return $this->state(fn (array $attributes) => [
            'financial_category_id' => $categoryId,
        ]);
    }
}
