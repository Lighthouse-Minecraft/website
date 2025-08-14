<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for creating Comment model instances with flexible relationship hooks.
 *
 * Supports attaching new or existing comments and authors.
 * Includes state methods for published/unpublished/scheduled comments.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    /**
     * Attach comment to an announcement (polymorphic).
     */
    public function forAnnouncement($announcement = null)
    {
        return $this->state(function () use ($announcement) {
            return [
                'commentable_id' => $announcement ? $announcement->id : Announcement::factory(),
                'commentable_type' => Announcement::class,
            ];
        });
    }

    /**
     * Attach comment to a blog (polymorphic).
     */
    public function forBlog($blog = null)
    {
        return $this->state(function () use ($blog) {
            return [
                'commentable_id' => $blog ? $blog->id : Blog::factory(),
                'commentable_type' => Blog::class,
            ];
        });
    }

    /**
     * Attach an author to the comment.
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
        return [
            'content' => $this->faker->realText(300),
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected']),
            'parent_id' => $this->faker->boolean(20) ? fn () => Comment::factory() : null, // 20% chance to be a reply
            'edited_at' => $this->faker->boolean(30) ? $this->faker->dateTimeBetween('-1 month', 'now') : null, // 30% chance edited
            'commentable_id' => fn () => Blog::factory(),
            'commentable_type' => Blog::class,
            'author_id' => fn () => User::factory(),
            'created_by' => fn () => User::factory(),
            'updated_by' => fn () => User::factory(),
        ];
    }
}
