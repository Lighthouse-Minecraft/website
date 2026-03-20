<?php

namespace App\Actions;

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use Lorisleiva\Actions\Concerns\AsAction;

class ArchiveBlogPost
{
    use AsAction;

    public function handle(BlogPost $post): BlogPost
    {
        $post->update(['status' => BlogPostStatus::Archived]);

        RecordActivity::run($post, 'blog_post_archived', "Blog post \"{$post->title}\" archived.");

        return $post->fresh();
    }
}
