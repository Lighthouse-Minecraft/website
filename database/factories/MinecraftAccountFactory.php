<?php

namespace Database\Factories;

use App\Enums\MinecraftAccountType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MinecraftAccount>
 */
class MinecraftAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'username' => 'Player'.fake()->numberBetween(100, 9999),
            'uuid' => fake()->uuid(),
            'account_type' => fake()->randomElement([MinecraftAccountType::Java, MinecraftAccountType::Bedrock]),
            'verified_at' => now(),
            'last_username_check_at' => null,
        ];
    }

    /**
     * Indicate that the account is Java Edition.
     */
    public function java(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => MinecraftAccountType::Java,
        ]);
    }

    /**
     * Indicate that the account is Bedrock Edition.
     */
    public function bedrock(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => MinecraftAccountType::Bedrock,
            'username' => '.Player'.fake()->numberBetween(100, 9999),
        ]);
    }
}
