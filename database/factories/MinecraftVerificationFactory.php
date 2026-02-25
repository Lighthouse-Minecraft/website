<?php

namespace Database\Factories;

use App\Enums\MinecraftAccountType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MinecraftVerification>
 */
class MinecraftVerificationFactory extends Factory
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
            'code' => strtoupper(Str::random(6)),
            'account_type' => fake()->randomElement([MinecraftAccountType::Java, MinecraftAccountType::Bedrock]),
            'minecraft_username' => fake()->userName(),
            'minecraft_uuid' => fake()->uuid(),
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
            'whitelisted_at' => now(),
        ];
    }

    /**
     * Indicate that the verification is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    /**
     * Indicate that the verification is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the verification is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expires_at' => now()->subMinutes(10),
        ]);
    }

    /**
     * Indicate that the verification is for Java Edition.
     */
    public function java(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => MinecraftAccountType::Java,
        ]);
    }

    /**
     * Indicate that the verification is for Bedrock Edition.
     */
    public function bedrock(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => MinecraftAccountType::Bedrock,
        ]);
    }
}
