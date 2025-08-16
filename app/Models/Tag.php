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
     * The table associated with the model.
     */
    protected $table = 'tags';

    /**
     * The announcements that belong to the tag.
     */
    public function announcements()
    {
        return $this->belongsToMany(Announcement::class, 'announcement_tag', 'tag_id', 'announcement_id');
    }

    /**
     * The blogs that belong to the tag.
     */
    public function blogs()
    {
        return $this->belongsToMany(Blog::class, 'blog_tag', 'tag_id', 'blog_id');
    }

    /**
     * Get the author of the tag.
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * The parent tag (for nested tags).
     */
    public function parent()
    {
        return $this->belongsTo(Tag::class, 'parent_id');
    }

    /**
     * The child tags (subtags).
     */
    public function children()
    {
        return $this->hasMany(Tag::class, 'parent_id');
    }

    /**
     * Scope for active tags.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // -------------------- Validation --------------------
    /**
     * Validate the Category model instance.
     * Checks for required name and unique name.
     */
    public function isValid(): bool
    {
        // Name is required
        if (empty($this->name)) {
            return false;
        }
        // Name must be unique (excluding current model)
        $query = Tag::where('name', $this->name);
        if ($this->exists) {
            $query->where('id', '!=', $this->id);
        }
        if ($query->exists()) {
            return false;
        }

        return true;
    }

    /**
     * Get validation errors for the Category model instance.
     * Returns an array of error messages for name and uniqueness.
     */
    public function getErrors(): array
    {
        $errors = [];
        if (empty($this->name)) {
            $errors['name'] = 'The name field is required.';
        } else {
            $query = Tag::where('name', $this->name);
            if ($this->exists) {
                $query->where('id', '!=', $this->id);
            }
            if ($query->exists()) {
                $errors['name'] = 'The name field must be unique.';
            }
        }

        return $errors;
    }
}
