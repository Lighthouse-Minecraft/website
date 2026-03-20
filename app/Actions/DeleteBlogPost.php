<?php

namespace App\Actions;

use App\Enums\ThreadStatus;
use App\Models\BlogPost;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteBlogPost
{
    use AsAction;

    public function handle(BlogPost $post): void
    {
        // Sync images with empty body to release all references before deleting
        SyncBlogPostImages::run($post, '');

        RecordActivity::run($post, 'blog_post_deleted', "Blog post \"{$post->title}\" soft-deleted.");

        // Close the comment thread if it exists
        $thread = $post->commentThread;
        if ($thread) {
            $thread->update([
                'status' => ThreadStatus::Closed,
                'closed_at' => now(),
            ]);
        }

        $post->delete();
    }
}
