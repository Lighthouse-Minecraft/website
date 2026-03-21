<?php

namespace App\Actions;

use App\Enums\MembershipLevel;
use App\Enums\MessageKind;
use App\Models\BlogPost;
use App\Models\Message;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class PostBlogComment
{
    use AsAction;

    public function handle(BlogPost $post, User $user, string $body): Message
    {
        $thread = $post->commentThread;

        if (! $thread) {
            $thread = CreateBlogCommentThread::run($post);
        }

        $requiresModeration = $this->requiresModeration($user);

        $message = Message::create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'body' => $body,
            'kind' => MessageKind::Message,
            'is_pending_moderation' => $requiresModeration,
        ]);

        $thread->update(['last_message_at' => now()]);

        return $message;
    }

    public function requiresModeration(User $user): bool
    {
        // Citizens never need moderation
        if ($user->membership_level === MembershipLevel::Citizen) {
            return false;
        }

        // Residents with 6+ months at Resident rank don't need moderation
        if ($user->membership_level === MembershipLevel::Resident && $user->resident_since) {
            if ($user->resident_since->diffInMonths(now()) >= 6) {
                return false;
            }
        }

        // Everyone else (Travelers, new Residents) needs moderation
        return true;
    }
}
