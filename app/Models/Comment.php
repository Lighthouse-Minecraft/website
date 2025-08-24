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
        'announcement_id',
        'user_id',
        'status',
        'parent_id',
        'edited_at',
    ];

    /**
     * The table associated with the model.
     */
    protected $table = 'comments';

    /**
     * The announcement this comment belongs to.
     */
    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }

    /**
     * The user who made the comment.
     */
    public function user()
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
