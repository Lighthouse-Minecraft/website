<?php

namespace Database\Factories;

use App\Models\FinancialOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FinancialOrganization>
 */
class FinancialOrganizationFactory extends Factory
{
    protected $model = FinancialOrganization::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
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
