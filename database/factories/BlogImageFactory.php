<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BlogImage>
 */
class BlogImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'alt_text' => $this->faker->sentence(),
            'path' => 'blog/images/'.$this->faker->uuid().'.jpg',
            'uploaded_by' => User::factory(),
            'unreferenced_at' => null,
        ];
    }
}
