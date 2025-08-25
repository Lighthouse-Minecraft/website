<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Taxonomy gateway for categories and tags.
 *
 * Provides unified access to category and tag models.
 * Extendable class for future advanced taxonomy features.
 */
class Taxonomy extends Model
{
    /**
     * Get all categories as a query builder.
     */
    public static function categories(): Builder
    {
        return Category::query();
    }

    /**
     * Get all tags as a query builder.
     */
    public static function tags(): Builder
    {
        return Tag::query();
    }

    /**
     * Get all categories as a collection.
     */
    public static function allCategories(): Collection
    {
        return self::categories()->get();
    }

    /**
     * Get all tags as a collection.
     */
    public static function allTags(): Collection
    {
        return self::tags()->get();
    }

    /**
     * Find a category by ID.
     */
    public static function findCategory(int $id): ?Category
    {
        return Category::find($id);
    }

    /**
     * Find a tag by ID.
     */
    public static function findTag(int $id): ?Tag
    {
        return Tag::find($id);
    }
}
