<?php

namespace App\Actions;

use App\Models\BlogPost;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteBlogPost
{
    use AsAction;

    public function handle(BlogPost $post): void
    {
        RecordActivity::run($post, 'blog_post_deleted', "Blog post \"{$post->title}\" soft-deleted.");

        $post->delete();
    }
}
