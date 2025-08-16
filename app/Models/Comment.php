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
        'announcement_id',
        'blog_id',
        'commentable_id',
        'commentable_type',
        'status',
        'parent_id',
        'edited_at',
    ];

    /**
     * The table associated with the model.
     */
    protected $table = 'comments';

    /**
     * Get the parent commentable model (announcement or blog).
     */
    public function commentable()
    {
        return $this->morphTo();
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
}
