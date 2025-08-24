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
        'description',
        'color',
        'created_by',
        'is_active',
    ];

    /**
     * The announcements that belong to the tag.
     */
    public function announcements()
    {
        return $this->belongsToMany(Announcement::class, 'announcement_tag', 'tag_id', 'announcement_id');
    }
}
