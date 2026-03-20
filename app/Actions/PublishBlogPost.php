<?php

namespace App\Actions;

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
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

        $this->updateFeaturedStories($post);

        return $post->fresh();
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
