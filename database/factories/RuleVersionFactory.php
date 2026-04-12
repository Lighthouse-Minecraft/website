<?php

namespace Database\Factories;

use App\Models\RuleVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RuleVersionFactory extends Factory
{
    protected $model = RuleVersion::class;

    public function definition(): array
    {
        return [
            'version_number' => $this->faker->unique()->numberBetween(1, 9999),
            'status' => 'draft',
            'created_by_user_id' => User::factory(),
            'approved_by_user_id' => null,
            'rejection_note' => null,
            'published_at' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(['status' => 'submitted']);
    }

    public function published(): static
    {
        return $this->state([
            'status' => 'published',
            'approved_by_user_id' => User::factory(),
            'published_at' => now(),
        ]);
    }
}
