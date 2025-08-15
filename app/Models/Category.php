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
}
