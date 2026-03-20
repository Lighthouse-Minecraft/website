<?php

namespace App\Actions;

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use App\Models\User;
use App\Notifications\BlogPostApprovedNotification;
use App\Services\TicketNotificationService;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class ApproveBlogPost
{
    use AsAction;

    public function handle(BlogPost $post, User $reviewer, ?Carbon $scheduledAt = null): BlogPost
    {
        if ($scheduledAt) {
            $post->update([
                'status' => BlogPostStatus::Scheduled,
                'scheduled_at' => $scheduledAt,
            ]);
        } else {
            $post->update([
                'status' => BlogPostStatus::Published,
                'published_at' => now(),
            ]);
        }

        RecordActivity::run($post, 'blog_post_approved', "Blog post \"{$post->title}\" approved by {$reviewer->name}.");

        $service = app(TicketNotificationService::class);
        $service->send($post->author, new BlogPostApprovedNotification($post, $reviewer), 'staff_alerts');

        return $post->fresh();
    }
}
