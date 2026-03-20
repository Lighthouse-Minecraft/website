<?php

namespace App\Models;

use App\Enums\BlogPostStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogPost extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'body',
        'hero_image_path',
        'meta_description',
        'og_image_path',
        'status',
        'scheduled_at',
        'published_at',
        'author_id',
        'category_id',
        'is_edited',
    ];

    protected function casts(): array
    {
        return [
            'status' => BlogPostStatus::class,
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'is_edited' => 'boolean',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag');
    }

    public function isDraft(): bool
    {
        return $this->status === BlogPostStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status === BlogPostStatus::Published;
    }

    public function heroImageUrl(): ?string
    {
        if (! $this->hero_image_path) {
            return null;
        }

        return \App\Services\StorageService::publicUrl($this->hero_image_path);
    }

    public function ogImageUrl(): ?string
    {
        if (! $this->og_image_path) {
            return null;
        }

        return \App\Services\StorageService::publicUrl($this->og_image_path);
    }
}
