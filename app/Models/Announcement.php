<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'title',
        'content',
        'author_id',
        'is_published',
        'published_at',
    ];

    /**
     * The table associated with the model.
     */
    protected $table = 'announcements';

    // -------------------- Relationships --------------------
    /**
     * The relationships to eager load by default.
     */
    protected $with = [
        'author',
        'comments',
        'tags',
        'categories',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    /**
     * Get the author of the announcement.
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the comments for the announcement.
     */
    public function comments()
    {
        return $this->hasMany(Comment::class, 'announcement_id');
    }

    /**
     * Get the tags for the announcement.
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'announcement_tag', 'announcement_id', 'tag_id');
    }

    /**
     * Get the categories for the announcement.
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'announcement_category', 'announcement_id', 'category_id');
    }

    // -------------------- Scopes --------------------
    /**
     * Scope a query to only include published announcements.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope a query to only include announcements published at or after a given date.
     */
    public function scopePublishedAt($query, $date)
    {
        return $query->where('published_at', '>=', $date);
    }

    /**
     * Scope a query to only include announcements by a specific author.
     */
    public function scopeByAuthor($query, $authorId)
    {
        return $query->where('author_id', $authorId);
    }

    /**
     * Scope a query to only include announcements with a specific category.
     */
    public function scopeWithCategory($query, $category)
    {
        return $query->whereHas('categories', function ($q) use ($category) {
            $q->where('name', $category);
        });
    }

    /**
     * Scope a query to only include announcements with a specific tag.
     */
    public function scopeWithTag($query, $tag)
    {
        return $query->whereHas('tags', function ($q) use ($tag) {
            $q->where('name', $tag);
        });
    }

    // -------------------- Attribute Accessors & Helpers --------------------
    /**
     * Get the formatted publication date.
     */
    public function publicationDate()
    {
        return $this->published_at->format('F j, Y');
    }

    /**
     * Return the first 3 lines of content; append "..." if more exist.
     * Works with either HTML or plain text input.
     */
    public function excerpt(int $linesToShow = 3): string
    {
        $content = (string) ($this->content ?? '');
        if ($content === '') {
            return '';
        }

        // Detect whether it looks like HTML
        $looksLikeHtml = (bool) preg_match('/<[^>]+>/', $content);

        if ($looksLikeHtml) {
            // Treat common HTML line boundaries as actual newlines *before* stripping tags
            $blockTags = '(p|div|section|article|header|footer|h[1-6]|ul|ol|li|blockquote|pre|table|thead|tbody|tr|td|th)'; // Add more as needed
            $content = preg_replace('/<\s*br\s*\/?>/i', "\n", $content);                   // <br> -> \n
            $content = preg_replace('/<\/\s*'.$blockTags.'\s*>/i', "\n", $content);    // </block> -> \n
            $content = strip_tags($content);
            $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Normalize line endings and whitespace for both HTML and plain text paths
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = trim($content);

        // If there's still no newline (pure single-paragraph plain text), soft-wrap so
        // we have "lines" to preview. Tweak width to taste.
        if (strpos($content, "\n") === false) {
            $content = wordwrap($content, 120, "\n", false);
        }

        // Split into lines and lightly trim each
        $lines = preg_split('/\n/', $content);
        $lines = array_map(fn ($l) => trim(preg_replace('/[ \t]+/', ' ', $l)), $lines);

        // Remove leading/trailing empty lines
        while (! empty($lines) && $lines[0] === '') {
            array_shift($lines);
        }
        while (! empty($lines) && end($lines) === '') {
            array_pop($lines);
        }

        if (empty($lines)) {
            return '';
        }

        $excerptLines = array_slice($lines, 0, $linesToShow);
        $excerpt = implode("\n", $excerptLines);

        if (count($lines) > $linesToShow) {
            $excerpt .= "\n...";
        }

        return $excerpt;
    }

    /**
     * Get the route for the announcement.
     */
    public function route()
    {
        return route('announcement.show', $this);
    }

    /**
     * Determine if the announcement is authored by a given user.
     */
    public function isAuthoredBy(User $user)
    {
        return $this->author_id === $user->id;
    }

    /**
     * Get the name of the author.
     */
    public function authorName()
    {
        return $this->author ? $this->author->name : 'Unknown Author';
    }

    // -------------------- String Representations --------------------
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

    // -------------------- Counts --------------------
    /**
     * Get the comments count.
     */
    public function commentsCount()
    {
        return $this->comments()->count();
    }

    /**
     * Get the categories count.
     */
    public function categoriesCount()
    {
        return $this->categories()->count();
    }

    /**
     * Get the tags count.
     */
    public function tagsCount()
    {
        return $this->tags()->count();
    }
}
