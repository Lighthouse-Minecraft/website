<?php

namespace Database\Factories;

use App\Enums\DiscordAccountStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DiscordAccount>
 */
class DiscordAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'discord_user_id' => (string) fake()->unique()->numberBetween(100000000000000000, 999999999999999999),
            'username' => 'discord_'.fake()->userName(),
            'global_name' => fake()->name(),
            'avatar_hash' => null,
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'token_expires_at' => now()->addDays(7),
            'status' => DiscordAccountStatus::Active,
            'verified_at' => now(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => DiscordAccountStatus::Active,
            'verified_at' => now(),
        ]);
    }

    public function brigged(): static
    {
        return $this->state(fn () => [
            'status' => DiscordAccountStatus::Brigged,
        ]);
    }
}
