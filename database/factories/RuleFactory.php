<?php

namespace Database\Factories;

use App\Models\Rule;
use App\Models\RuleCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RuleFactory extends Factory
{
    protected $model = Rule::class;

    public function definition(): array
    {
        return [
            'rule_category_id' => RuleCategory::factory(),
            'title' => $this->faker->sentence(6),
            'description' => $this->faker->paragraph(),
            'status' => 'active',
            'supersedes_rule_id' => null,
            'created_by_user_id' => User::factory(),
            'sort_order' => $this->faker->numberBetween(1, 100),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
