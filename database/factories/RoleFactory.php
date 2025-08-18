<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Member', 'Moderator', 'Editor', 'Reviewer', 'Helper']),
            'description' => fake()->sentence(),
            'color' => fake()->hexColor(),
            'icon' => fake()->randomElement(['user', 'star', 'shield-exclamation', 'pencil', 'heart']),
        ];
    }

    public function admin(): self
    {
        return $this->state([
            'name' => 'Admin',
            'description' => 'Administrator role with full permissions',
            'color' => '#ef4444',
            'icon' => 'shield-check',
        ]);
    }
}
