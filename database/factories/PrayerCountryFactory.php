<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PrayerCountry>
 */
class PrayerCountryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'day' => $this->faker->date('m-d'),
            'name' => $this->faker->word,
            'operation_world_url' => $this->faker->url,
            'prayer_cast_url' => $this->faker->url,
        ];
    }

    public function withDay(string $day): static
    {
        return $this->state([
            'day' => $day,
        ]);
    }

    public function withName(string $name): static
    {
        return $this->state([
            'name' => $name,
        ]);
    }

    public function withOperationWorldUrl(string $url): static
    {
        return $this->state([
            'operation_world_url' => $url,
        ]);
    }

    public function withPrayerCastUrl(string $url): static
    {
        return $this->state([
            'prayer_cast_url' => $url,
        ]);
    }
}
