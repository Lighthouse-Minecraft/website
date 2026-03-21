<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'content',
        'hero_image_path',
        'include_in_sitemap',
    ];

    protected function casts(): array
    {
        return [
            'include_in_sitemap' => 'boolean',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'category_id');
    }

    public function heroImageUrl(): ?string
    {
        if (! $this->hero_image_path) {
            return null;
        }

        return \App\Services\StorageService::publicUrl($this->hero_image_path);
    }
}
