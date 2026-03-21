<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateUserSlug
{
    use AsAction;

    public function handle(string $name, ?int $excludeUserId = null): string
    {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'user';
        }

        $slug = $baseSlug;
        $suffix = 2;

        while ($this->slugExists($slug, $excludeUserId)) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    protected function slugExists(string $slug, ?int $excludeUserId): bool
    {
        $query = User::where('slug', $slug);

        if ($excludeUserId) {
            $query->where('id', '!=', $excludeUserId);
        }

        return $query->exists();
    }
}
