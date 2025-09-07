<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PrayerCountryStat>
 */
class PrayerCountryStatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prayer_country_id' => 1,
            'year' => $this->faker->year(),
            'count' => $this->faker->numberBetween(1, 100),
        ];
    }
}
