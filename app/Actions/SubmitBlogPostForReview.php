<?php

namespace App\Actions;

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use App\Models\User;
use App\Notifications\BlogPostSubmittedForReviewNotification;
use App\Services\TicketNotificationService;
use Lorisleiva\Actions\Concerns\AsAction;

class SubmitBlogPostForReview
{
    use AsAction;

    public function handle(BlogPost $post, User $submitter): BlogPost
    {
        $post->update(['status' => BlogPostStatus::InReview]);

        RecordActivity::run($post, 'blog_post_submitted_for_review', "Blog post \"{$post->title}\" submitted for review by {$submitter->name}.");

        $service = app(TicketNotificationService::class);
        $blogAuthors = User::whereHas('staffPosition.roles', function ($query) {
            $query->where('name', 'Blog - Author');
        })->where('id', '!=', $submitter->id)->get();

        foreach ($blogAuthors as $author) {
            $service->send($author, new BlogPostSubmittedForReviewNotification($post), 'staff_alerts');
        }

        return $post->fresh();
    }
}
