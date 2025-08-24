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
        'description',
        'color',
        'created_by',
        'is_active',
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
