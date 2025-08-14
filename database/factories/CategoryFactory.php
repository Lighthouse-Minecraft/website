<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for creating Category model instances with flexible relationship hooks.
 *
 * Supports attaching new or existing categories and authors.
 * Includes state methods for published/unpublished/scheduled categories.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Attach categories to an announcement (many-to-many).
     */
    public function withAnnouncements(int $count = 1)
    {
        return $this->afterCreating(function (Category $category) use ($count) {
            $announcements = Announcement::factory()->count($count)->create();
            foreach ($announcements as $announcement) {
                $announcement->categories()->attach($category->id);
            }
        });
    }

    /**
     * Attach categories to a blog (many-to-many).
     */
    public function withBlogs(int $count = 1)
    {
        return $this->afterCreating(function (Category $category) use ($count) {
            $blogs = Blog::factory()->count($count)->create();
            foreach ($blogs as $blog) {
                $blog->categories()->attach($category->id);
            }
        });
    }

    /**
     * Attach an author to the category.
     */
    public function withAuthor()
    {
        return $this->state(function () {
            return [
                'author_id' => User::factory(),
            ];
        });
    }

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);
        $palette = ['#1abc9c', '#3498db', '#9b59b6', '#e67e22', '#e74c3c', '#34495e', '#f1c40f'];

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => $this->faker->realText(120),
            'color' => $this->faker->randomElement($palette),
            'author_id' => fn () => User::factory(),
            'is_active' => $this->faker->boolean(95),
            'parent_id' => $this->faker->boolean(15) ? fn () => Category::factory() : null, // 15% chance to be a child category
            'created_by' => fn () => User::factory(),
            'updated_by' => fn () => User::factory(),
        ];
    }
}
