<?php

namespace Database\Factories;

use App\Enums\BackgroundCheckStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BackgroundCheck>
 */
class BackgroundCheckFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'run_by_user_id' => User::factory(),
            'service' => $this->faker->company().' Background Services',
            'completed_date' => $this->faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'status' => BackgroundCheckStatus::Pending,
            'notes' => null,
            'locked_at' => null,
        ];
    }

    public function passed(): static
    {
        return $this->state(fn () => [
            'status' => BackgroundCheckStatus::Passed,
            'locked_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => BackgroundCheckStatus::Failed,
            'locked_at' => now(),
        ]);
    }

    public function waived(): static
    {
        return $this->state(fn () => [
            'status' => BackgroundCheckStatus::Waived,
            'locked_at' => now(),
        ]);
    }

    public function deliberating(): static
    {
        return $this->state(fn () => [
            'status' => BackgroundCheckStatus::Deliberating,
            'locked_at' => null,
        ]);
    }
}
