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
        $uuid = fake()->uuid();

        return [
            'user_id' => User::factory(),
            'username' => 'Player'.fake()->numberBetween(100, 9999),
            'uuid' => $uuid,
            'avatar_url' => 'https://mc-heads.net/avatar/'.str_replace('-', '', $uuid),
            'account_type' => fake()->randomElement([MinecraftAccountType::Java, MinecraftAccountType::Bedrock]),
            'status' => 'active',
            'command_id' => 'Player'.fake()->numberBetween(100, 9999),
            'verified_at' => now(),
            'last_username_check_at' => null,
        ];
    }

    /**
     * Indicate that the account is Java Edition.
     */
    public function java(): static
    {
        return $this->state(fn () => [
            'account_type' => MinecraftAccountType::Java,
        ]);
    }

    /**
     * Indicate that the account is Bedrock Edition.
     */
    public function bedrock(): static
    {
        return $this->state(fn () => [
            'account_type' => MinecraftAccountType::Bedrock,
            'username' => '.Player'.fake()->numberBetween(100, 9999),
        ]);
    }

    /**
     * Indicate that the account is in verifying status.
     */
    public function verifying(): static
    {
        return $this->state(fn () => [
            'status' => 'verifying',
            'verified_at' => null,
        ]);
    }

    /**
     * Indicate that the account is active.
     */
    public function active(): static
    {
        return $this->state(fn () => [
            'status' => 'active',
            'verified_at' => now(),
        ]);
    }
}
