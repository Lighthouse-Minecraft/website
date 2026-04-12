<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Credential>
 */
class CredentialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' Admin',
            'website_url' => $this->faker->optional()->url(),
            'username' => $this->faker->userName(),
            'email' => $this->faker->optional()->safeEmail(),
            'password' => $this->faker->password(12),
            'totp_secret' => null,
            'notes' => $this->faker->optional()->sentence(),
            'recovery_codes' => null,
            'needs_password_change' => false,
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function withTotp(): static
    {
        return $this->state(fn (array $attributes) => [
            'totp_secret' => strtoupper($this->faker->lexify('????????????????????????????????')),
        ]);
    }

    public function needsPasswordChange(): static
    {
        return $this->state(fn (array $attributes) => [
            'needs_password_change' => true,
        ]);
    }
}
