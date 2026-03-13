<?php

namespace Database\Factories;

use App\Enums\CommunityQuestionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CommunityQuestion>
 */
class CommunityQuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'question_text' => $this->faker->sentence().'?',
            'description' => $this->faker->optional()->paragraph(),
            'status' => CommunityQuestionStatus::Draft,
            'start_date' => null,
            'end_date' => null,
            'created_by' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CommunityQuestionStatus::Active,
            'start_date' => now()->subDays(3),
            'end_date' => now()->addDays(4),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CommunityQuestionStatus::Scheduled,
            'start_date' => now()->addDays(7),
            'end_date' => now()->addDays(14),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CommunityQuestionStatus::Archived,
            'start_date' => now()->subDays(14),
            'end_date' => now()->subDays(7),
        ]);
    }
}
