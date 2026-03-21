<?php

namespace App\Actions;

use App\Models\Message;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class RejectBlogComment
{
    use AsAction;

    public function handle(Message $message, User $moderator): void
    {
        RecordActivity::run($message, 'blog_comment_rejected', "Blog comment rejected by {$moderator->name}.");

        $message->delete();
    }
}
