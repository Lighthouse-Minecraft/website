<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class FinancialTagFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'color' => fake()->randomElement(['zinc', 'red', 'orange', 'yellow', 'green', 'blue', 'indigo', 'purple', 'pink']),
        ];
    }
}
