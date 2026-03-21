<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BlogCategory>
 */
class BlogCategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'content' => $this->faker->optional()->paragraph(),
            'hero_image_path' => null,
            'include_in_sitemap' => true,
        ];
    }
}
