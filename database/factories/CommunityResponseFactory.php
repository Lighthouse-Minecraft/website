<?php

namespace Database\Factories;

use App\Enums\CommunityResponseStatus;
use App\Models\CommunityQuestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CommunityResponse>
 */
class CommunityResponseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'community_question_id' => CommunityQuestion::factory(),
            'user_id' => User::factory(),
            'body' => $this->faker->paragraphs(2, true),
            'status' => CommunityResponseStatus::Submitted,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CommunityResponseStatus::Approved,
            'approved_at' => now(),
            'reviewed_at' => now(),
            'reviewed_by' => User::factory(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CommunityResponseStatus::Rejected,
            'reviewed_at' => now(),
            'reviewed_by' => User::factory(),
        ]);
    }
}
