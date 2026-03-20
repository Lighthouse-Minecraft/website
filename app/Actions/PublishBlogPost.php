<?php

namespace App\Actions;

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use Lorisleiva\Actions\Concerns\AsAction;

class PublishBlogPost
{
    use AsAction;

    public function handle(BlogPost $post): BlogPost
    {
        $post->update([
            'status' => BlogPostStatus::Published,
            'published_at' => now(),
        ]);

        RecordActivity::run($post, 'blog_post_published', "Blog post \"{$post->title}\" published.");

        return $post->fresh();
    }
}
