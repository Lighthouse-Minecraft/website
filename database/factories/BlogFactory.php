<?php

namespace Database\Factories;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for creating Blog model instances with flexible relationship hooks.
 *
 * Supports attaching new or existing categories, tags, comments, and authors.
 * Includes state methods for published/unpublished/scheduled blogs.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Blog>
 */
class BlogFactory extends Factory
{
    // -------------------- Author Relationship Methods --------------------
    /**
     * Create a blog with a specific author.
     *
     * @return $this
     */
    public function byAuthor(User $author): static
    {
        return $this->state(fn (array $attributes) => [
            'author_id' => $author->id,
        ]);
    }

    /**
     * Attach a specific author to the blog.
     *
     * @return $this
     */
    public function forAuthor(User $author): static
    {
        return $this->state(fn () => ['author_id' => $author->id]);
    }

    // -------------------- Category Relationship Methods --------------------
    /**
     * Attach existing categories to the blog after creation.
     *
     * @param  array|\Illuminate\Support\Collection  $categoryIds
     * @return $this
     */
    public function withExistingCategories($categoryIds)
    {
        return $this->afterCreating(function (Blog $blog) use ($categoryIds) {
            $blog->categories()->sync(collect($categoryIds)->toArray());
        });
    }

    /**
     * Attach new categories to the blog after creation.
     *
     * @return $this
     */
    public function withCategories(int $count = 1)
    {
        return $this->afterCreating(function (Blog $blog) use ($count) {
            $categories = Category::factory()->count($count)->create();
            $blog->categories()->sync($categories->pluck('id')->toArray());
        });
    }

    // -------------------- Comment Relationship Methods --------------------
    /**
     * Attach existing comments to the blog after creation.
     *
     * @param  array|\Illuminate\Support\Collection  $commentIds
     * @return $this
     */
    public function withExistingComments($commentIds)
    {
        return $this->afterCreating(function (Blog $blog) use ($commentIds) {
            foreach (collect($commentIds) as $id) {
                $comment = Comment::find($id);
                if ($comment) {
                    $comment->commentable()->associate($blog);
                    $comment->save();
                }
            }
        });
    }

    /**
     * Attach new comments to the blog after creation.
     *
     * @return $this
     */
    public function withComments(int $count = 1, array $attributes = []): static
    {
        return $this->afterCreating(function (Blog $blog) use ($count, $attributes) {
            Comment::factory()->count($count)->create(array_merge($attributes, [
                'commentable_id' => $blog->id,
                'commentable_type' => Blog::class,
            ]));
        });
    }

    // -------------------- Tag Relationship Methods --------------------
    /**
     * Attach existing tags to the blog after creation.
     *
     * @param  array|\Illuminate\Support\Collection  $tagIds
     * @return $this
     */
    public function withExistingTags($tagIds)
    {
        return $this->afterCreating(function (Blog $blog) use ($tagIds) {
            $blog->tags()->sync(collect($tagIds)->toArray());
        });
    }

    /**
     * Attach new tags to the blog after creation.
     *
     * @return $this
     */
    public function withTags(int $count = 1): static
    {
        return $this->afterCreating(function (Blog $blog) use ($count) {
            $tags = Tag::factory()->count($count)->create();
            $blog->tags()->sync($tags->pluck('id')->toArray());
        });
    }

    // -------------------- State & Content Methods --------------------
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(6);

        return [
            'title' => $title,
            'content' => $this->faker->paragraphs(3, true),
            'slug' => Str::slug($title),
            'published_at' => $this->faker->optional(0.7)->dateTimeThisYear(),
            'is_published' => $this->faker->boolean(80),
            'author_id' => User::factory(),
            'category_id' => Category::factory(),
        ];
    }

    /**
     * Create a blog with minimal content.
     *
     * @return $this
     */
    public function minimal(): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => $this->faker->sentence(),
        ]);
    }

    /**
     * Indicate that the blog should be published.
     *
     * @return $this
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
            'published_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
        ]);
    }

    /**
     * Indicate that the blog should be unpublished.
     *
     * @return $this
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    /**
     * Indicate that the blog should be published in the future.
     *
     * @return $this
     */
    public function scheduledForFuture(): static
    {
        return $this->state(fn (array $attributes) => [
            'published' => false,
            'published_at' => $this->faker->dateTimeBetween('now', '+1 month'),
        ]);
    }

    /**
     * Create a blog with a long title.
     *
     * @return $this
     */
    public function withLongTitle(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => $this->faker->sentence(12, true),
        ]);
    }

    /**
     * Create a blog with rich HTML content.
     *
     * @return $this
     */
    public function withRichContent(): static
    {
        $content = '<h2>'.$this->faker->sentence().'</h2>';
        $content .= '<p>'.$this->faker->paragraph().'</p>';
        $content .= '<ul>';
        for ($i = 0; $i < 3; $i++) {
            $content .= '<li>'.$this->faker->sentence().'</li>';
        }
        $content .= '</ul>';
        $content .= '<p>'.$this->faker->paragraph().'</p>';
        $content .= '<blockquote>'.$this->faker->sentence().'</blockquote>';

        return $this->state(fn (array $attributes) => [
            'content' => $content,
        ]);
    }
}
