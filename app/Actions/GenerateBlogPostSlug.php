<?php

namespace App\Actions;

use App\Models\BlogPost;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateBlogPostSlug
{
    use AsAction;

    public function handle(string $title, ?int $excludePostId = null): string
    {
        $baseSlug = Str::slug($title);

        if ($baseSlug === '') {
            $baseSlug = 'post';
        }

        $slug = $baseSlug;
        $suffix = 2;

        while ($this->slugExists($slug, $excludePostId)) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    protected function slugExists(string $slug, ?int $excludePostId): bool
    {
        $query = BlogPost::withTrashed()->where('slug', $slug);

        if ($excludePostId) {
            $query->where('id', '!=', $excludePostId);
        }

        return $query->exists();
    }
}
