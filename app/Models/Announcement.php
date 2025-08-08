<?php

namespace App\Models;

use Illuminate\Database\Eloquent\{Model, Relations\BelongsTo};
use Illuminate\Support\{Str};

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'content',
        'author_id',
        'is_published',
    ];

    /**
     * Get the author of the announcement post.
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }

    /**
     * Scope a query to only include published announcement posts.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope a query to only include announcement posts by a specific author.
     */
    public function scopeByAuthor($query, $authorId)
    {
        return $query->where('author_id', $authorId);
    }

    /**
     * Scope a query to only include announcement posts with a specific tag.
     */
    public function scopeWithTag($query, $tag)
    {
        return $query->whereHas('tags', function ($q) use ($tag) {
            $q->where('name', $tag);
        });
    }

    /**
     * Get the publication date of the announcement post.
     */
    public function publicationDate()
    {
        return $this->created_at->format('F j, Y');
    }

    /**
     * Get the excerpt of the announcement post.
     */
    public function excerpt($length = 150)
    {
        return Str::limit(strip_tags($this->content), $length);
    }

    /**
     * Get the full URL of the announcement post.
     */
    public function fullUrl()
    {
        return url('/announcements/' . $this->id . '/' . $this->slug);
    }

    /**
     * Check if the announcement post is authored by the given user.
     */
    public function isAuthoredBy(User $user)
    {
        return $this->author_id === $user->id;
    }

    /**
     * Get the comments count for the announcement post.
     */
    public function commentsCount()
    {
        return $this->comments()->count();
    }

    /**
     * Get the tags as a comma-separated string.
     */
    public function tagsAsString()
    {
        return $this->tags->pluck('name')->implode(', ');
    }

    /**
     * Get the categories as a comma-separated string.
     */
    public function categoriesAsString()
    {
        return $this->categories->pluck('name')->implode(', ');
    }

    /**
     * Get the author name.
     */
    public function authorName()
    {
        return $this->author ? $this->author->name : 'Unknown Author';
    }
}
