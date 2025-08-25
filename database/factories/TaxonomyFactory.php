<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Support\Str;

/**
 * TaxonomyFactory helper
 *
 * This is a convenience factory-like helper for generating taxonomy-related
 * data (categories, tags) along with attached Blogs and Announcements.
 *
 * Note: This is NOT an Eloquent model factory (doesn't extend Factory) because
 * App\Models\Taxonomy is a gateway, not a persisted model. Use these helpers in
 * seeders/tests where bundling related records is useful.
 */
final class TaxonomyFactory
{
    /**
     * Create and persist a Category with optional related blogs/announcements.
     *
     * @param  array{name?: string, slug?: string, description?: string, color?: string, author_id?: int}  $attributes
     */
    public static function createCategory(array $attributes = [], int $blogs = 0, int $announcements = 0): Category
    {
        if (isset($attributes['name']) && ! isset($attributes['slug'])) {
            $attributes['slug'] = Str::slug($attributes['name']);
        }

        $category = Category::factory()->create($attributes);

        if ($blogs > 0) {
            Blog::factory()->count($blogs)->create()->each(function (Blog $blog) use ($category): void {
                $blog->categories()->attach($category->id);
            });
        }

        if ($announcements > 0) {
            Announcement::factory()->count($announcements)->create()->each(function (Announcement $a) use ($category): void {
                $a->categories()->attach($category->id);
            });
        }

        return $category;
    }

    /**
     * Create and persist a Tag with optional related blogs/announcements.
     *
     * @param  array{name?: string, slug?: string, description?: string, color?: string, author_id?: int}  $attributes
     */
    public static function createTag(array $attributes = [], int $blogs = 0, int $announcements = 0): Tag
    {
        if (isset($attributes['name']) && ! isset($attributes['slug'])) {
            $attributes['slug'] = Str::slug($attributes['name']);
        }

        $tag = Tag::factory()->create($attributes);

        if ($blogs > 0) {
            Blog::factory()->count($blogs)->create()->each(function (Blog $blog) use ($tag): void {
                $blog->tags()->attach($tag->id);
            });
        }

        if ($announcements > 0) {
            Announcement::factory()->count($announcements)->create()->each(function (Announcement $a) use ($tag): void {
                $a->tags()->attach($tag->id);
            });
        }

        return $tag;
    }

    /**
     * Seed a small taxonomy set for tests: a few categories and tags, each attached
     * to some blogs/announcements. Returns created records for further assertions.
     *
     * @return array{categories: array<int, Category>, tags: array<int, Tag>}
     */
    public static function createSet(int $categories = 2, int $tags = 3, int $blogsPer = 1, int $announcementsPer = 1): array
    {
        $cats = [];
        for ($i = 0; $i < $categories; $i++) {
            $cats[] = self::createCategory([], $blogsPer, $announcementsPer);
        }

        $tagsArr = [];
        for ($i = 0; $i < $tags; $i++) {
            $tagsArr[] = self::createTag([], $blogsPer, $announcementsPer);
        }

        return [
            'categories' => $cats,
            'tags' => $tagsArr,
        ];
    }
}
