<?php

namespace App\Jobs;

use App\Actions\DeleteBlogImage;
use App\Models\BlogImage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CleanupUnreferencedBlogImages implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $images = BlogImage::whereNotNull('unreferenced_at')
            ->where('unreferenced_at', '<=', now()->subDays(30))
            ->get();

        foreach ($images as $image) {
            DeleteBlogImage::run($image);
        }
    }
}
