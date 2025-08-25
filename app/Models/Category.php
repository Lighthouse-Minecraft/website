<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
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
    protected $table = 'categories';

    /**
     * The announcements that belong to the category.
     */
    public function announcements()
    {
        return $this->belongsToMany(Announcement::class, 'announcement_category', 'category_id', 'announcement_id');
    }

    /**
     * The blogs that belong to the category.
     */
    public function blogs()
    {
        return $this->belongsToMany(Blog::class, 'blog_category', 'category_id', 'blog_id');
    }

    /**
     * Get the author of the category.
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * The parent category (for nested categories).
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * The child categories (subcategories).
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Scope for active categories.
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
        $query = Category::where('name', $this->name);
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
            $query = Category::where('name', $this->name);
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
