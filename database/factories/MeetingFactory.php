<?php

namespace Database\Factories;

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
            'summary' => $this->faker->paragraph(),
            'day' => $this->faker->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'is_public' => true,
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
}
