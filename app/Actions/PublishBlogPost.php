<?php

namespace App\Actions;

use App\Enums\BlogPostStatus;
use App\Enums\MembershipLevel;
use App\Models\BlogPost;
use App\Models\User;
use App\Notifications\BlogPostPublishedNotification;
use App\Notifications\CommunityStoryFeaturedNotification;
use App\Services\TicketNotificationService;
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

        CreateBlogCommentThread::run($post);

        $this->updateFeaturedStories($post);
        $this->notifySubscribers($post);

        return $post->fresh();
    }

    protected function notifySubscribers(BlogPost $post): void
    {
        $service = app(TicketNotificationService::class);

        $users = User::where('membership_level', '>=', MembershipLevel::Traveler->value)
            ->where('in_brig', false)
            ->get();

        foreach ($users as $user) {
            $service->send($user, new BlogPostPublishedNotification($post), 'blog');
        }
    }

    protected function updateFeaturedStories(BlogPost $post): void
    {
        $responses = $post->communityResponses()->with('user')->get();

        if ($responses->isEmpty()) {
            return;
        }

        $blogUrl = route('blog.show', $post->slug);
        $service = app(TicketNotificationService::class);

        foreach ($responses as $response) {
            $response->update(['featured_in_blog_url' => $blogUrl]);

            $service->send(
                $response->user,
                new CommunityStoryFeaturedNotification($post, $response),
                'announcements',
            );
        }
    }
}
