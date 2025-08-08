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
            'day' => $this->faker->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'is_public' => true,
        ];
    }

    /**
     * Indicate that the meeting is private.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
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
