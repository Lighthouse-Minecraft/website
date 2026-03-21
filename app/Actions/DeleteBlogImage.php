<?php

namespace App\Actions;

use App\Models\BlogImage;
use Illuminate\Support\Facades\DB;
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

        DB::transaction(function () use ($image, $title) {
            RecordActivity::run($image, 'blog_image_deleted', "Blog image \"{$title}\" deleted.");
            $image->delete();
        });

        // Delete file after DB transaction commits so a DB failure doesn't orphan the file
        Storage::disk(config('filesystems.public_disk'))->delete($path);
    }
}
