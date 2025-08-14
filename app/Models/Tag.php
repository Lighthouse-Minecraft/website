<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tag model for categorizing announcements.
 * Allows users to filter and sort announcements by tag.
 */
class Tag extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'tags';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
        'author_id',
        'is_active',
        'parent_id',
        'created_by',
        'updated_by',
    ];

    /**
     * Get all of the models that are assigned this tag (announcements, blogs).
     */
    public function taggables()
    {
        return $this->morphedByMany(Announcement::class, 'taggable')
            ->union($this->morphedByMany(Blog::class, 'taggable'));
    }

    public function announcements()
    {
        return $this->morphedByMany(Announcement::class, 'taggable');
    }

    public function blogs()
    {
        return $this->morphedByMany(Blog::class, 'taggable');
    }
}
