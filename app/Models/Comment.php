<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Comment model for user feedback on announcements.
 * Supports linking comments to announcements and users.
 */
class Comment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'content',
        'author_id',
        'commentable_id',
        'commentable_type',
        'commentable_title',
        'commentable_content',
        'status',
        'parent_id',
        'edited_at',
        'reviewed_by',
        'reviewed_at',
        'needs_review',
    ];

    /**
     * The table associated with the model.
     */
    protected $table = 'comments';

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    /**
     * Get the parent commentable model (announcement or blog).
     */
    public function commentable()
    {
        return $this->morphTo();
    }

    /**
     * Accessor: normalize legacy polymorphic types to our aliases.
     * Ensures values like "App\\Models\\Blog" or "Blog" resolve to "blog".
     */
    public function getCommentableTypeAttribute($value)
    {
        if (! is_string($value)) {
            return $value;
        }

        $normalized = match ($value) {
            'App\\Models\\Blog', Blog::class, 'Blog' => 'blog',
            'App\\Models\\Announcement', Announcement::class, 'Announcement' => 'announcement',
            default => strtolower($value),
        };

        return $normalized;
    }

    /**
     * Mutator: store polymorphic types using our lowercase aliases.
     */
    public function setCommentableTypeAttribute($value): void
    {
        if (is_string($value)) {
            // Convert FQCNs or class basenames to lowercase alias
            $alias = strtolower(class_basename($value));
            if (in_array($alias, ['blog', 'announcement'], true)) {
                $this->attributes['commentable_type'] = $alias;

                return;
            }
        }

        $this->attributes['commentable_type'] = $value;
    }

    /**
     * The author who made the comment.
     */
    public function author()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The parent comment (for threaded/nested comments).
     */
    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * The child comments (replies).
     */
    public function children()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    /**
     * Scope for approved comments.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    // -------------------- Validation --------------------
    /**
     * Validate the Comment model instance.
     * Checks for required content and unique content.
     */
    public function isValid(): bool
    {
        // Content is required
        if (empty($this->content)) {
            return false;
        }
        // Content length must not exceed 2000 characters
        if (strlen($this->content) > 2000) {
            return false;
        }
        // Content must be unique (excluding current model)
        $query = Comment::where('content', $this->content);
        if ($this->exists) {
            $query->where('id', '!=', $this->id);
        }
        if ($query->exists()) {
            return false;
        }

        return true;
    }

    /**
     * Get validation errors for the Comment model instance.
     * Returns an array of error messages for content and uniqueness.
     */
    public function getErrors(): array
    {
        $errors = [];
        if (empty($this->content)) {
            $errors['content'] = 'The content field is required.';
        } else {
            if (strlen($this->content) > 2000) {
                $errors['content'] = 'The content may not be greater than 2000 characters.';
            } else {
                $query = Comment::where('content', $this->content);
                if ($this->exists) {
                    $query->where('id', '!=', $this->id);
                }
                if ($query->exists()) {
                    $errors['content'] = 'The content field must be unique.';
                }
            }
        }

        return $errors;
    }

    /**
     * The reviewer who marked the comment as reviewed.
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
