<?php

namespace App\Jobs;

use App\Actions\PublishBlogPost;
use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishScheduledPosts implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $posts = BlogPost::where('status', BlogPostStatus::Scheduled)
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($posts as $post) {
            PublishBlogPost::run($post);
        }
    }
}
