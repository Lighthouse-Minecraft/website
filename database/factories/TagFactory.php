<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for creating Tag model instances with flexible relationship hooks.
 *
 * Supports attaching new or existing tags and authors.
 * Includes state methods for published/unpublished/scheduled tags.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    /**
     * Attach tag to an announcement (many-to-many).
     */
    public function forAnnouncement($announcement = null)
    {
        return $this->afterCreating(function (Tag $tag) use ($announcement) {
            $announcement = $announcement ?: Announcement::factory()->create();
            $announcement->tags()->attach($tag->id);
        });
    }

    /**
     * Attach tag to a blog (many-to-many).
     */
    public function forBlog($blog = null)
    {
        return $this->afterCreating(function (Tag $tag) use ($blog) {
            $blog = $blog ?: Blog::factory()->create();
            $blog->tags()->attach($tag->id);
        });
    }

    /**
     * Attach an author to the tag.
     */
    public function withAuthor($author = null)
    {
        return $this->state(function () use ($author) {
            return [
                'author_id' => $author ? $author->id : User::factory(),
            ];
        });
    }

    public function definition(): array
    {
        $name = $this->faker->unique()->word;

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence,
            'color' => $this->faker->hexColor,
            'author_id' => User::factory(),
            'is_active' => $this->faker->boolean(90),
            'created_by' => fn () => User::factory(),
            'updated_by' => fn () => User::factory(),
        ];
    }
}
