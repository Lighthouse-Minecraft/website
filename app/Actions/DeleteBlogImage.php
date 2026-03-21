<?php

namespace App\Actions;

use App\Models\BlogImage;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteBlogImage
{
    use AsAction;

    public function handle(BlogImage $image): void
    {
        if ($image->posts()->count() > 0) {
            throw new \RuntimeException('Cannot delete a blog image that is still referenced by posts.');
        }

        $title = $image->title;
        $path = $image->path;

        // Delete file first to avoid orphaned files if DB delete fails
        Storage::disk(config('filesystems.public_disk'))->delete($path);

        RecordActivity::run($image, 'blog_image_deleted', "Blog image \"{$title}\" deleted.");

        $image->delete();
    }
}
