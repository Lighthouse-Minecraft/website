<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(6, true),
            'content' => $this->faker->paragraphs(3, true),
            'author_id' => User::factory(),
            'is_published' => true,
            'published_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'expired_at' => null,
            'notifications_sent_at' => null,
        ];
    }

    /**
     * Indicate that the announcement should be published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
            'published_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
        ]);
    }

    /**
     * Indicate that the announcement should be unpublished.
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    /**
     * Indicate that the announcement should be published in the future.
     */
    public function scheduledForFuture(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
            'published_at' => $this->faker->dateTimeBetween('now', '+1 month'),
        ]);
    }

    /**
     * Create an announcement with a specific author.
     */
    public function byAuthor(User $author): static
    {
        return $this->state(fn (array $attributes) => [
            'author_id' => $author->id,
        ]);
    }

    /**
     * Create an announcement with rich markdown content.
     */
    public function withRichContent(): static
    {
        $content = "## {$this->faker->sentence()}\n\n";
        $content .= "{$this->faker->paragraph()}\n\n";
        $content .= "- {$this->faker->sentence()}\n";
        $content .= "- {$this->faker->sentence()}\n";
        $content .= "- {$this->faker->sentence()}\n\n";
        $content .= "{$this->faker->paragraph()}\n\n";
        $content .= "> {$this->faker->sentence()}";

        return $this->state(fn (array $attributes) => [
            'content' => $content,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
            'published_at' => now()->subDays(7),
            'expired_at' => now()->subDay(),
        ]);
    }

    public function expiredAt(\DateTimeInterface $date): static
    {
        return $this->state(fn (array $attributes) => [
            'expired_at' => $date,
        ]);
    }

    /**
     * Create an announcement with a long title.
     */
    public function withLongTitle(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => $this->faker->sentence(12, true),
        ]);
    }

    /**
     * Create an announcement with minimal content.
     */
    public function minimal(): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => $this->faker->sentence(),
        ]);
    }
}
