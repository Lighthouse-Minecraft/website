<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BlogImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'alt_text',
        'path',
        'uploaded_by',
        'unreferenced_at',
    ];

    protected function casts(): array
    {
        return [
            'unreferenced_at' => 'datetime',
        ];
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class, 'blog_image_post')
            ->withPivot('created_at');
    }

    public function url(): string
    {
        return \App\Services\StorageService::publicUrl($this->path);
    }
}
