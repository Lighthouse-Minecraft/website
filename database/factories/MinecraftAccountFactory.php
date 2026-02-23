<?php

namespace Database\Factories;

use App\Enums\MinecraftAccountStatus;
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
        $username = 'Player'.fake()->numberBetween(100, 9999);

        return [
            'user_id' => User::factory(),
            'username' => $username,
            'uuid' => $uuid,
            'avatar_url' => 'https://mc-heads.net/avatar/'.str_replace('-', '', $uuid),
            'account_type' => MinecraftAccountType::Java,
            'status' => MinecraftAccountStatus::Active,
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
            'status' => MinecraftAccountStatus::Verifying,
            'verified_at' => null,
        ]);
    }

    /**
     * Indicate that the account is active.
     */
    public function active(): static
    {
        return $this->state(fn () => [
            'status' => MinecraftAccountStatus::Active,
            'verified_at' => now(),
        ]);
    }
}
