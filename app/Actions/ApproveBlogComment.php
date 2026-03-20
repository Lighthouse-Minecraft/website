<?php

namespace App\Actions;

use App\Models\Message;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class ApproveBlogComment
{
    use AsAction;

    public function handle(Message $message, User $moderator): void
    {
        $message->update(['is_pending_moderation' => false]);

        RecordActivity::run($message, 'blog_comment_approved', "Blog comment approved by {$moderator->name}.");
    }
}
