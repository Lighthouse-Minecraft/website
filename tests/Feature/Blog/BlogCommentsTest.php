<?php

declare(strict_types=1);

use App\Actions\ApproveBlogComment;
use App\Actions\CreateBlogCommentThread;
use App\Actions\DeleteBlogPost;
use App\Actions\PostBlogComment;
use App\Actions\PublishBlogPost;
use App\Actions\RejectBlogComment;
use App\Enums\BlogPostStatus;
use App\Enums\MembershipLevel;
use App\Enums\MessageKind;
use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\BlogPost;
use App\Models\Message;
use App\Models\User;
use App\Notifications\BlogPostPublishedNotification;
use Illuminate\Support\Facades\Notification;

uses()->group('blog', 'comments');

// === ThreadType Enum ===

it('has BlogComment value in ThreadType enum', function () {
    expect(ThreadType::BlogComment->value)->toBe('blog_comment')
        ->and(ThreadType::BlogComment->label())->toBe('Blog Comment');
});

// === Thread Auto-Creation ===

it('creates a comment thread when a blog post is published', function () {
    $author = User::factory()->withRole('Blog - Author')->create();
    $post = BlogPost::factory()->create([
        'author_id' => $author->id,
        'status' => BlogPostStatus::InReview,
    ]);

    PublishBlogPost::run($post);

    $post->refresh();
    $thread = $post->commentThread;

    expect($thread)->not->toBeNull()
        ->and($thread->type)->toBe(ThreadType::BlogComment)
        ->and($thread->topicable_type)->toBe(BlogPost::class)
        ->and($thread->topicable_id)->toBe($post->id)
        ->and($thread->status)->toBe(ThreadStatus::Open);
});

it('does not create duplicate threads on re-publish', function () {
    $author = User::factory()->withRole('Blog - Author')->create();
    $post = BlogPost::factory()->create(['author_id' => $author->id]);

    CreateBlogCommentThread::run($post);
    CreateBlogCommentThread::run($post);

    expect($post->commentThread()->count())->toBe(1);
});

// === Comment Posting by Membership Level ===

it('allows Citizens to post comments', function () {
    $citizen = User::factory()->withMembershipLevel(MembershipLevel::Citizen)->create();
    $post = BlogPost::factory()->published()->create();
    CreateBlogCommentThread::run($post);

    $message = PostBlogComment::run($post, $citizen, 'Great post!');

    expect($message)->toBeInstanceOf(Message::class)
        ->and($message->body)->toBe('Great post!')
        ->and($message->is_pending_moderation)->toBeFalse();
});

it('allows Residents with 6+ months to post without moderation', function () {
    $resident = User::factory()->withMembershipLevel(MembershipLevel::Resident)->create([
        'resident_since' => now()->subMonths(7),
    ]);
    $post = BlogPost::factory()->published()->create();
    CreateBlogCommentThread::run($post);

    $message = PostBlogComment::run($post, $resident, 'Nice article!');

    expect($message->is_pending_moderation)->toBeFalse();
});

it('queues comments from Residents under 6 months for moderation', function () {
    $resident = User::factory()->withMembershipLevel(MembershipLevel::Resident)->create([
        'resident_since' => now()->subMonths(3),
    ]);
    $post = BlogPost::factory()->published()->create();
    CreateBlogCommentThread::run($post);

    $message = PostBlogComment::run($post, $resident, 'Testing moderation.');

    expect($message->is_pending_moderation)->toBeTrue();
});

it('queues comments from Travelers for moderation', function () {
    $traveler = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create();
    $post = BlogPost::factory()->published()->create();
    CreateBlogCommentThread::run($post);

    $message = PostBlogComment::run($post, $traveler, 'Hello from a Traveler!');

    expect($message->is_pending_moderation)->toBeTrue();
});

it('queues comments from Residents with no resident_since set for moderation', function () {
    $resident = User::factory()->withMembershipLevel(MembershipLevel::Resident)->create([
        'resident_since' => null,
    ]);
    $post = BlogPost::factory()->published()->create();
    CreateBlogCommentThread::run($post);

    $message = PostBlogComment::run($post, $resident, 'Testing null resident_since.');

    expect($message->is_pending_moderation)->toBeTrue();
});

// === Authorization Gates ===

it('allows Traveler+ users not in brig to comment via gate', function () {
    $traveler = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create();
    loginAs($traveler);

    expect($traveler->can('post-blog-comment'))->toBeTrue();
});

it('denies Stowaways from commenting via gate', function () {
    $stowaway = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create();
    loginAs($stowaway);

    expect($stowaway->can('post-blog-comment'))->toBeFalse();
});

it('denies Drifters from commenting via gate', function () {
    $drifter = User::factory()->withMembershipLevel(MembershipLevel::Drifter)->create();
    loginAs($drifter);

    expect($drifter->can('post-blog-comment'))->toBeFalse();
});

it('denies users in brig from commenting via gate', function () {
    $user = User::factory()->withMembershipLevel(MembershipLevel::Citizen)->create([
        'in_brig' => true,
    ]);
    loginAs($user);

    expect($user->can('post-blog-comment'))->toBeFalse();
});

// === Comment Moderation Actions ===

it('approves a pending blog comment', function () {
    $post = BlogPost::factory()->published()->create();
    CreateBlogCommentThread::run($post);
    $traveler = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create();
    $message = PostBlogComment::run($post, $traveler, 'Pending comment');
    $moderator = loginAsAdmin();

    expect($message->is_pending_moderation)->toBeTrue();

    ApproveBlogComment::run($message, $moderator);

    expect($message->fresh()->is_pending_moderation)->toBeFalse();
});

it('rejects a pending blog comment by deleting it', function () {
    $post = BlogPost::factory()->published()->create();
    CreateBlogCommentThread::run($post);
    $traveler = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create();
    $message = PostBlogComment::run($post, $traveler, 'Bad comment');
    $moderator = loginAsAdmin();

    RejectBlogComment::run($message, $moderator);

    expect(Message::find($message->id))->toBeNull();
});

// === Thread Lifecycle ===

it('closes comment thread when blog post is soft-deleted', function () {
    $author = User::factory()->withRole('Blog - Author')->create();
    $post = BlogPost::factory()->published()->create(['author_id' => $author->id]);
    CreateBlogCommentThread::run($post);

    $thread = $post->commentThread;
    expect($thread->status)->toBe(ThreadStatus::Open);

    DeleteBlogPost::run($post);

    expect($thread->fresh()->status)->toBe(ThreadStatus::Closed)
        ->and($thread->fresh()->closed_at)->not->toBeNull();
});

// === Notification Dispatch ===

it('sends BlogPostPublishedNotification to opted-in Traveler+ users', function () {
    Notification::fake();

    $author = User::factory()->withRole('Blog - Author')->create();
    $traveler = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create();
    $citizen = User::factory()->withMembershipLevel(MembershipLevel::Citizen)->create();
    $stowaway = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create();

    $post = BlogPost::factory()->create([
        'author_id' => $author->id,
        'status' => BlogPostStatus::InReview,
    ]);

    PublishBlogPost::run($post);

    Notification::assertSentTo($traveler, BlogPostPublishedNotification::class);
    Notification::assertSentTo($citizen, BlogPostPublishedNotification::class);
    Notification::assertNotSentTo($stowaway, BlogPostPublishedNotification::class);
});

it('does not send blog notification to users in brig', function () {
    Notification::fake();

    $author = User::factory()->withRole('Blog - Author')->create();
    $brigUser = User::factory()->withMembershipLevel(MembershipLevel::Citizen)->create([
        'in_brig' => true,
    ]);

    $post = BlogPost::factory()->create([
        'author_id' => $author->id,
        'status' => BlogPostStatus::InReview,
    ]);

    PublishBlogPost::run($post);

    Notification::assertNotSentTo($brigUser, BlogPostPublishedNotification::class);
});

it('respects blog notification preference opt-out', function () {
    Notification::fake();

    $author = User::factory()->withRole('Blog - Author')->create();
    $optedOut = User::factory()->withMembershipLevel(MembershipLevel::Citizen)->create([
        'notification_preferences' => [
            'blog' => ['email' => false, 'pushover' => false, 'discord' => false],
        ],
    ]);

    $post = BlogPost::factory()->create([
        'author_id' => $author->id,
        'status' => BlogPostStatus::InReview,
    ]);

    PublishBlogPost::run($post);

    // TicketNotificationService skips calling notify when no channels are available
    Notification::assertNotSentTo($optedOut, BlogPostPublishedNotification::class);
});

// === Comment Display ===

it('only shows approved comments publicly', function () {
    $post = BlogPost::factory()->published()->create();
    CreateBlogCommentThread::run($post);

    $citizen = User::factory()->withMembershipLevel(MembershipLevel::Citizen)->create();
    $traveler = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create();

    // Citizen comment publishes immediately
    $approved = PostBlogComment::run($post, $citizen, 'Approved comment');
    // Traveler comment is queued
    $pending = PostBlogComment::run($post, $traveler, 'Pending comment');

    $thread = $post->commentThread;
    $publicComments = $thread->messages()
        ->where('kind', MessageKind::Message)
        ->where('is_pending_moderation', false)
        ->get();

    expect($publicComments)->toHaveCount(1)
        ->and($publicComments->first()->body)->toBe('Approved comment');
});

// === Moderate Blog Comments Gate ===

it('allows users with Moderator role to moderate comments', function () {
    $moderator = User::factory()->withRole('Moderator')->create();
    loginAs($moderator);

    expect($moderator->can('moderate-blog-comments'))->toBeTrue();
});

it('allows users with Blog - Author role to moderate comments', function () {
    $author = User::factory()->withRole('Blog - Author')->create();
    loginAs($author);

    expect($author->can('moderate-blog-comments'))->toBeTrue();
});

it('denies regular users from moderating comments', function () {
    $user = User::factory()->withMembershipLevel(MembershipLevel::Citizen)->create();
    loginAs($user);

    expect($user->can('moderate-blog-comments'))->toBeFalse();
});
