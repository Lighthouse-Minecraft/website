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
        $blogAuthorRoleId = \App\Models\Role::where('name', 'Blog - Author')->value('id');
        $blogAuthors = $blogAuthorRoleId ? User::where('id', '!=', $submitter->id)
            ->where(function ($q) use ($blogAuthorRoleId) {
                // Position-based role
                $q->whereHas('staffPosition.roles', fn ($r) => $r->where('roles.id', $blogAuthorRoleId))
                // Or position with Allow All
                    ->orWhereHas('staffPosition', fn ($p) => $p->whereNotNull('has_all_roles_at'))
                // Or rank-based role
                    ->orWhereIn('staff_rank', function ($sub) use ($blogAuthorRoleId) {
                        $sub->select('staff_rank')->from('role_staff_rank')->where('role_id', $blogAuthorRoleId);
                    });
            })
            ->get() : collect();

        foreach ($blogAuthors as $author) {
            $service->send($author, new BlogPostSubmittedForReviewNotification($post), 'staff_alerts');
        }

        return $post->fresh();
    }
}
