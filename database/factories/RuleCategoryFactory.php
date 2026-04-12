<?php

namespace Database\Factories;

use App\Models\RuleCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class RuleCategoryFactory extends Factory
{
    protected $model = RuleCategory::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(3, true),
            'sort_order' => $this->faker->numberBetween(1, 100),
        ];
    }
}
