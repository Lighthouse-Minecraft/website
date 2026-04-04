<?php

namespace Database\Factories;

use App\Models\FinancialTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FinancialTag>
 */
class FinancialTagFactory extends Factory
{
    protected $model = FinancialTag::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'created_by' => User::factory(),
            'is_archived' => false,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_archived' => true,
        ]);
    }
}
