<?php

declare(strict_types=1);

use App\Actions\ApproveBlogPost;
use App\Actions\ArchiveBlogPost;
use App\Actions\PublishBlogPost;
use App\Actions\SubmitBlogPostForReview;
use App\Actions\UpdateBlogPost;
use App\Enums\BlogPostStatus;
use App\Jobs\PublishScheduledPosts;
use App\Models\BlogPost;
use App\Models\User;
use App\Notifications\BlogPostApprovedNotification;
use App\Notifications\BlogPostSubmittedForReviewNotification;
use Illuminate\Support\Facades\Notification;

uses()->group('blog', 'actions', 'publishing');

// === SubmitBlogPostForReview ===

it('transitions a draft post to in-review status', function () {
    $author = User::factory()->withRole('Blog - Author')->create();
    $post = BlogPost::factory()->create(['author_id' => $author->id]);

    $result = SubmitBlogPostForReview::run($post, $author);

    expect($result->status)->toBe(BlogPostStatus::InReview);
});

it('records activity when submitting for review', function () {
    $author = User::factory()->withRole('Blog - Author')->create();
    $post = BlogPost::factory()->create(['author_id' => $author->id]);

    SubmitBlogPostForReview::run($post, $author);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => BlogPost::class,
        'subject_id' => $post->id,
        'action' => 'blog_post_submitted_for_review',
    ]);
});

it('sends review notification to other blog authors', function () {
    Notification::fake();

    $author = User::factory()->withRole('Blog - Author')->create();
    $reviewer = User::factory()->withRole('Blog - Author')->create();
    $post = BlogPost::factory()->create(['author_id' => $author->id]);

    SubmitBlogPostForReview::run($post, $author);

    Notification::assertSentTo($reviewer, BlogPostSubmittedForReviewNotification::class);
});

it('does not send review notification to the submitter', function () {
    Notification::fake();

    $author = User::factory()->withRole('Blog - Author')->create();
    $post = BlogPost::factory()->create(['author_id' => $author->id]);

    SubmitBlogPostForReview::run($post, $author);

    Notification::assertNotSentTo($author, BlogPostSubmittedForReviewNotification::class);
});

// === ApproveBlogPost ===

it('publishes immediately when no scheduled_at is provided', function () {
    $author = User::factory()->create();
    $reviewer = User::factory()->withRole('Blog - Author')->create();
    $post = BlogPost::factory()->inReview()->create(['author_id' => $author->id]);

    $result = ApproveBlogPost::run($post, $reviewer);

    expect($result->status)->toBe(BlogPostStatus::Published)
        ->and($result->published_at)->not->toBeNull();
});

it('schedules post when scheduled_at is provided', function () {
    $author = User::factory()->create();
    $reviewer = User::factory()->withRole('Blog - Author')->create();
    $post = BlogPost::factory()->inReview()->create(['author_id' => $author->id]);
    $scheduledAt = now()->addDays(3);

    $result = ApproveBlogPost::run($post, $reviewer, $scheduledAt);

    expect($result->status)->toBe(BlogPostStatus::Scheduled)
        ->and($result->scheduled_at->toDateTimeString())->toBe($scheduledAt->toDateTimeString());
});

it('records activity when approving a post', function () {
    $author = User::factory()->create();
    $reviewer = User::factory()->withRole('Blog - Author')->create();
    $post = BlogPost::factory()->inReview()->create(['author_id' => $author->id]);

    ApproveBlogPost::run($post, $reviewer);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => BlogPost::class,
        'subject_id' => $post->id,
        'action' => 'blog_post_approved',
    ]);
});

it('sends approval notification to the post author', function () {
    Notification::fake();

    $author = User::factory()->create();
    $reviewer = User::factory()->withRole('Blog - Author')->create();
    $post = BlogPost::factory()->inReview()->create(['author_id' => $author->id]);

    ApproveBlogPost::run($post, $reviewer);

    Notification::assertSentTo($author, BlogPostApprovedNotification::class);
});

// === PublishBlogPost ===

it('publishes a post and sets published_at', function () {
    $post = BlogPost::factory()->scheduled()->create();

    $result = PublishBlogPost::run($post);

    expect($result->status)->toBe(BlogPostStatus::Published)
        ->and($result->published_at)->not->toBeNull();
});

it('records activity when publishing a post', function () {
    $post = BlogPost::factory()->scheduled()->create();

    PublishBlogPost::run($post);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => BlogPost::class,
        'subject_id' => $post->id,
        'action' => 'blog_post_published',
    ]);
});

// === ArchiveBlogPost ===

it('archives a published post', function () {
    $post = BlogPost::factory()->published()->create();

    $result = ArchiveBlogPost::run($post);

    expect($result->status)->toBe(BlogPostStatus::Archived);
});

it('records activity when archiving a post', function () {
    $post = BlogPost::factory()->published()->create();

    ArchiveBlogPost::run($post);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => BlogPost::class,
        'subject_id' => $post->id,
        'action' => 'blog_post_archived',
    ]);
});

// === PublishScheduledPosts Job ===

it('publishes posts whose scheduled_at has passed', function () {
    $post = BlogPost::factory()->create([
        'status' => BlogPostStatus::Scheduled,
        'scheduled_at' => now()->subHour(),
    ]);

    (new PublishScheduledPosts)->handle();

    expect($post->fresh()->status)->toBe(BlogPostStatus::Published)
        ->and($post->fresh()->published_at)->not->toBeNull();
});

it('does not publish posts whose scheduled_at is in the future', function () {
    $post = BlogPost::factory()->create([
        'status' => BlogPostStatus::Scheduled,
        'scheduled_at' => now()->addHour(),
    ]);

    (new PublishScheduledPosts)->handle();

    expect($post->fresh()->status)->toBe(BlogPostStatus::Scheduled);
});

it('does not publish draft posts', function () {
    $post = BlogPost::factory()->create([
        'status' => BlogPostStatus::Draft,
        'scheduled_at' => now()->subHour(),
    ]);

    (new PublishScheduledPosts)->handle();

    expect($post->fresh()->status)->toBe(BlogPostStatus::Draft);
});

it('publishes multiple scheduled posts at once', function () {
    $post1 = BlogPost::factory()->create([
        'status' => BlogPostStatus::Scheduled,
        'scheduled_at' => now()->subHours(2),
    ]);
    $post2 = BlogPost::factory()->create([
        'status' => BlogPostStatus::Scheduled,
        'scheduled_at' => now()->subMinutes(5),
    ]);

    (new PublishScheduledPosts)->handle();

    expect($post1->fresh()->status)->toBe(BlogPostStatus::Published)
        ->and($post2->fresh()->status)->toBe(BlogPostStatus::Published);
});

// === is_edited tracking ===

it('sets is_edited when editing a published post', function () {
    $post = BlogPost::factory()->published()->create(['is_edited' => false]);

    $updated = UpdateBlogPost::run($post, ['body' => 'Updated content after publish']);

    expect($updated->is_edited)->toBeTrue();
});

it('does not set is_edited when editing a draft post', function () {
    $post = BlogPost::factory()->create(['is_edited' => false, 'status' => BlogPostStatus::Draft]);

    $updated = UpdateBlogPost::run($post, ['body' => 'Updated draft content']);

    expect($updated->is_edited)->toBeFalse();
});

// === Soft-delete behavior ===

it('soft deletes a post preserving it in withTrashed', function () {
    $post = BlogPost::factory()->published()->create();

    $post->delete();

    expect(BlogPost::find($post->id))->toBeNull()
        ->and(BlogPost::withTrashed()->find($post->id))->not->toBeNull()
        ->and(BlogPost::withTrashed()->find($post->id)->deleted_at)->not->toBeNull();
});
