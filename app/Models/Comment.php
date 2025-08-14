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
}
