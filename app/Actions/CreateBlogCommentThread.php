<?php

namespace App\Actions;

use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\BlogPost;
use App\Models\Thread;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateBlogCommentThread
{
    use AsAction;

    public function handle(BlogPost $post): Thread
    {
        // Don't create duplicate threads
        $existing = $post->commentThread()->first();
        if ($existing) {
            return $existing;
        }

        $thread = Thread::create([
            'type' => ThreadType::BlogComment,
            'subject' => "Comments: {$post->title}",
            'status' => ThreadStatus::Open,
            'created_by_user_id' => $post->author_id,
            'topicable_type' => BlogPost::class,
            'topicable_id' => $post->id,
            'last_message_at' => now(),
        ]);

        return $thread;
    }
}
