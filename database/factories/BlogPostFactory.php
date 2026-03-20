<?php

namespace Database\Factories;

use App\Enums\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BlogPost>
 */
class BlogPostFactory extends Factory
{
    public function definition(): array
    {
        $title = $this->faker->sentence();

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.$this->faker->unique()->randomNumber(5),
            'body' => $this->faker->paragraphs(3, true),
            'hero_image_id' => null,
            'meta_description' => $this->faker->optional()->sentence(),
            'og_image_id' => null,
            'status' => BlogPostStatus::Draft,
            'scheduled_at' => null,
            'published_at' => null,
            'author_id' => User::factory(),
            'category_id' => null,
            'is_edited' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BlogPostStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BlogPostStatus::Scheduled,
            'scheduled_at' => now()->addDays(3),
        ]);
    }

    public function inReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BlogPostStatus::InReview,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BlogPostStatus::Archived,
        ]);
    }

    public function withCategory(): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => BlogCategory::factory(),
        ]);
    }
}
