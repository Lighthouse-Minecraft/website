<?php

namespace Database\Factories;

use App\Enums\MeetingStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Meeting>
 */
class MeetingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'scheduled_time' => $this->faker->dateTimeBetween('-1 month', '+1 month'),
            'start_time' => $this->faker->dateTimeBetween('-1 month', '+1 month'),
            'end_time' => $this->faker->dateTimeBetween('-1 month', '+1 month'),
            'summary' => $this->faker->text(255),
            'day' => $this->faker->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'is_public' => true,
            'status' => MeetingStatus::Pending,
            'agenda' => $this->faker->paragraph(),
            'minutes' => null,
            'community_minutes' => $this->faker->paragraph(),
        ];
    }

    /**
     * Indicate that the meeting is private.
     */
    public function private(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'is_public' => false,
            ];
        });
    }

    public function withStatus(MeetingStatus $status): Factory
    {
        return $this->state(function (array $attributes) use ($status) {
            return [
                'status' => $status,
            ];
        });
    }

    public function withAgenda(string $agenda): Factory
    {
        return $this->state(function (array $attributes) use ($agenda) {
            return [
                'agenda' => $agenda,
            ];
        });
    }

    public function withMinutes(string $minutes): Factory
    {
        return $this->state(function (array $attributes) use ($minutes) {
            return [
                'minutes' => $minutes,
            ];
        });
    }

    public function withCommunityMinutes(string $communityMinutes): Factory
    {
        return $this->state(function (array $attributes) use ($communityMinutes) {
            return [
                'community_minutes' => $communityMinutes,
            ];
        });
    }
}
