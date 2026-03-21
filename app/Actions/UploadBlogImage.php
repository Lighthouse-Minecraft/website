<?php

namespace App\Actions;

use App\Models\BlogImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Lorisleiva\Actions\Concerns\AsAction;

class UploadBlogImage
{
    use AsAction;

    public function handle(User $uploader, UploadedFile $file, string $title, string $altText): BlogImage
    {
        $path = $file->store('blog/images', config('filesystems.public_disk'));

        $image = BlogImage::create([
            'title' => $title,
            'alt_text' => $altText,
            'path' => $path,
            'uploaded_by' => $uploader->id,
        ]);

        RecordActivity::run($image, 'blog_image_uploaded', "Blog image \"{$title}\" uploaded by {$uploader->name}.");

        return $image;
    }
}
