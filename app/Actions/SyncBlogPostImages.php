<?php

namespace App\Actions;

use App\Models\BlogImage;
use App\Models\BlogPost;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncBlogPostImages
{
    use AsAction;

    public function handle(BlogPost $post, ?string $body = null): void
    {
        $referencedIds = $this->parseImageIds($body ?? $post->body ?? '');

        // Collect hero_image_id and og_image_id if they exist on the post
        if ($post->hero_image_id ?? null) {
            $referencedIds[] = $post->hero_image_id;
        }

        if ($post->og_image_id ?? null) {
            $referencedIds[] = $post->og_image_id;
        }

        $referencedIds = array_unique(array_filter($referencedIds));

        // Only include IDs that actually exist in the blog_images table
        $validIds = BlogImage::whereIn('id', $referencedIds)->pluck('id')->all();

        // Get the current pivot references for this post
        $currentIds = $post->images()->pluck('blog_images.id')->all();

        // Determine added and removed
        $addedIds = array_diff($validIds, $currentIds);
        $removedIds = array_diff($currentIds, $validIds);

        // Sync the pivot table
        $post->images()->sync($validIds);

        // Handle unreferenced_at for images that lost references
        if (! empty($removedIds)) {
            $this->markUnreferencedIfOrphaned($removedIds);
        }

        // Clear unreferenced_at for images that gained references
        if (! empty($addedIds)) {
            BlogImage::whereIn('id', $addedIds)
                ->whereNotNull('unreferenced_at')
                ->update(['unreferenced_at' => null]);
        }
    }

    protected function parseImageIds(string $body): array
    {
        preg_match_all('/\{\{image:(\d+)(?:\|[^}]*)?\}\}/', $body, $matches);

        return array_map('intval', $matches[1] ?? []);
    }

    protected function markUnreferencedIfOrphaned(array $imageIds): void
    {
        foreach ($imageIds as $imageId) {
            $image = BlogImage::find($imageId);

            if (! $image) {
                continue;
            }

            // Check if this image still has references from any post
            $referenceCount = $image->posts()->count();

            if ($referenceCount === 0) {
                $image->update(['unreferenced_at' => now()]);
            }
        }
    }
}
