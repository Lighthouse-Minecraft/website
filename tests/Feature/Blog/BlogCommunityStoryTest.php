<?php

declare(strict_types=1);

use App\Actions\ApproveBlogPost;
use App\Actions\CreateBlogPost;
use App\Actions\PublishBlogPost;
use App\Actions\UpdateBlogPost;
use App\Models\BlogPost;
use App\Models\CommunityQuestion;
use App\Models\CommunityResponse;
use App\Models\User;
use App\Notifications\CommunityStoryFeaturedNotification;
use Illuminate\Support\Facades\Notification;

uses()->group('blog', 'community-story');

// === Story Marker Parsing ===

it('replaces a valid story marker with a styled blockquote card', function () {
    $response = CommunityResponse::factory()->approved()->create([
        'body' => 'This is my community story.',
    ]);

    $post = BlogPost::factory()->create([
        'body' => "Hello world\n\n{{story:{$response->id}}}\n\nEnd of post",
    ]);

    $rendered = $post->renderBody();

    expect($rendered)->toContain('<blockquote')
        ->and($rendered)->toContain('This is my community story.')
        ->and($rendered)->toContain($response->user->name);
});

it('renders nothing for an invalid story ID', function () {
    $post = BlogPost::factory()->create([
        'body' => "Hello world\n\n{{story:99999}}\n\nEnd of post",
    ]);

    $rendered = $post->renderBody();

    expect($rendered)->not->toContain('{{story:')
        ->and($rendered)->not->toContain('<blockquote')
        ->and($rendered)->toContain('Hello world')
        ->and($rendered)->toContain('End of post');
});

it('renders nothing for a missing story ID of zero', function () {
    $post = BlogPost::factory()->create([
        'body' => 'Before {{story:0}} After',
    ]);

    $rendered = $post->renderBody();

    expect($rendered)->not->toContain('{{story:')
        ->and($rendered)->not->toContain('<blockquote');
});

it('replaces multiple story markers in the same post', function () {
    $question = CommunityQuestion::factory()->active()->create();
    $response1 = CommunityResponse::factory()->approved()->create([
        'community_question_id' => $question->id,
        'body' => 'First story content.',
    ]);
    $response2 = CommunityResponse::factory()->approved()->create([
        'community_question_id' => $question->id,
        'body' => 'Second story content.',
    ]);

    $post = BlogPost::factory()->create([
        'body' => "Intro\n\n{{story:{$response1->id}}}\n\nMiddle\n\n{{story:{$response2->id}}}\n\nEnd",
    ]);

    $rendered = $post->renderBody();

    expect($rendered)->toContain('First story content.')
        ->and($rendered)->toContain('Second story content.')
        ->and($rendered)->toContain($response1->user->name)
        ->and($rendered)->toContain($response2->user->name);
});

it('displays the response author avatar in the story card when available', function () {
    $response = CommunityResponse::factory()->approved()->create([
        'body' => 'A story with avatar.',
    ]);

    $post = BlogPost::factory()->create([
        'body' => "{{story:{$response->id}}}",
    ]);

    $rendered = $post->renderBody();

    // Either an img tag or a fallback initial div should be present
    expect($rendered)->toContain('<blockquote')
        ->and($rendered)->toContain($response->user->name);
});

it('gracefully handles a mix of valid and invalid story markers', function () {
    $response = CommunityResponse::factory()->approved()->create([
        'body' => 'Valid story.',
    ]);

    $post = BlogPost::factory()->create([
        'body' => "{{story:{$response->id}}}\n\n{{story:99999}}",
    ]);

    $rendered = $post->renderBody();

    expect($rendered)->toContain('Valid story.')
        ->and($rendered)->not->toContain('{{story:');
});

// === Pivot Management ===

it('creates a blog post with community responses attached', function () {
    $author = User::factory()->create();
    $question = CommunityQuestion::factory()->active()->create();
    $response = CommunityResponse::factory()->approved()->create([
        'community_question_id' => $question->id,
    ]);

    $post = CreateBlogPost::run($author, [
        'title' => 'Story Post',
        'body' => "{{story:{$response->id}}}",
        'community_question_id' => $question->id,
        'community_response_ids' => [$response->id],
    ]);

    expect($post->community_question_id)->toBe($question->id)
        ->and($post->communityResponses)->toHaveCount(1)
        ->and($post->communityResponses->first()->id)->toBe($response->id);
});

it('updates community responses on an existing blog post', function () {
    $question = CommunityQuestion::factory()->active()->create();
    $response1 = CommunityResponse::factory()->approved()->create([
        'community_question_id' => $question->id,
    ]);
    $response2 = CommunityResponse::factory()->approved()->create([
        'community_question_id' => $question->id,
    ]);

    $post = BlogPost::factory()->create([
        'community_question_id' => $question->id,
    ]);
    $post->communityResponses()->sync([$response1->id => ['sort_order' => 0]]);

    $updated = UpdateBlogPost::run($post, [
        'community_response_ids' => [$response2->id],
    ]);

    expect($updated->communityResponses)->toHaveCount(1)
        ->and($updated->communityResponses->first()->id)->toBe($response2->id);
});

it('stores sort order in the pivot table', function () {
    $question = CommunityQuestion::factory()->active()->create();
    $response1 = CommunityResponse::factory()->approved()->create([
        'community_question_id' => $question->id,
    ]);
    $response2 = CommunityResponse::factory()->approved()->create([
        'community_question_id' => $question->id,
    ]);

    $post = CreateBlogPost::run(User::factory()->create(), [
        'title' => 'Ordered Stories',
        'body' => 'Body text',
        'community_question_id' => $question->id,
        'community_response_ids' => [$response2->id, $response1->id],
    ]);

    $pivots = $post->communityResponses->pluck('pivot.sort_order', 'id');

    expect($pivots[$response2->id])->toBe(0)
        ->and($pivots[$response1->id])->toBe(1);
});

// === Featured in Blog URL Updates ===

it('updates featured_in_blog_url on community responses when post is published', function () {
    $question = CommunityQuestion::factory()->active()->create();
    $response = CommunityResponse::factory()->approved()->create([
        'community_question_id' => $question->id,
        'featured_in_blog_url' => null,
    ]);

    $post = BlogPost::factory()->create([
        'community_question_id' => $question->id,
    ]);
    $post->communityResponses()->sync([$response->id => ['sort_order' => 0]]);

    PublishBlogPost::run($post);

    expect($response->fresh()->featured_in_blog_url)->toBe(route('blog.show', $post->slug));
});

it('updates featured_in_blog_url when approved for immediate publish', function () {
    $question = CommunityQuestion::factory()->active()->create();
    $response = CommunityResponse::factory()->approved()->create([
        'community_question_id' => $question->id,
        'featured_in_blog_url' => null,
    ]);

    $reviewer = User::factory()->withRole('Blog Author')->create();
    $post = BlogPost::factory()->inReview()->create([
        'community_question_id' => $question->id,
    ]);
    $post->communityResponses()->sync([$response->id => ['sort_order' => 0]]);

    ApproveBlogPost::run($post, $reviewer);

    expect($response->fresh()->featured_in_blog_url)->toBe(route('blog.show', $post->slug));
});

it('does not update featured_in_blog_url when scheduled for later', function () {
    $question = CommunityQuestion::factory()->active()->create();
    $response = CommunityResponse::factory()->approved()->create([
        'community_question_id' => $question->id,
        'featured_in_blog_url' => null,
    ]);

    $reviewer = User::factory()->withRole('Blog Author')->create();
    $post = BlogPost::factory()->inReview()->create([
        'community_question_id' => $question->id,
    ]);
    $post->communityResponses()->sync([$response->id => ['sort_order' => 0]]);

    ApproveBlogPost::run($post, $reviewer, now()->addDays(3));

    expect($response->fresh()->featured_in_blog_url)->toBeNull();
});

// === Notification Dispatch ===

it('sends CommunityStoryFeaturedNotification to response authors on publish', function () {
    $question = CommunityQuestion::factory()->active()->create();
    $responseAuthor = User::factory()->create();
    $response = CommunityResponse::factory()->approved()->create([
        'community_question_id' => $question->id,
        'user_id' => $responseAuthor->id,
    ]);

    $post = BlogPost::factory()->create([
        'community_question_id' => $question->id,
    ]);
    $post->communityResponses()->sync([$response->id => ['sort_order' => 0]]);

    PublishBlogPost::run($post);

    Notification::assertSentTo($responseAuthor, CommunityStoryFeaturedNotification::class);
});

it('sends notifications to multiple response authors on publish', function () {
    $question = CommunityQuestion::factory()->active()->create();
    $author1 = User::factory()->create();
    $author2 = User::factory()->create();
    $response1 = CommunityResponse::factory()->approved()->create([
        'community_question_id' => $question->id,
        'user_id' => $author1->id,
    ]);
    $response2 = CommunityResponse::factory()->approved()->create([
        'community_question_id' => $question->id,
        'user_id' => $author2->id,
    ]);

    $post = BlogPost::factory()->create([
        'community_question_id' => $question->id,
    ]);
    $post->communityResponses()->sync([
        $response1->id => ['sort_order' => 0],
        $response2->id => ['sort_order' => 1],
    ]);

    PublishBlogPost::run($post);

    Notification::assertSentTo($author1, CommunityStoryFeaturedNotification::class);
    Notification::assertSentTo($author2, CommunityStoryFeaturedNotification::class);
});

it('does not send story notification when post has no community responses', function () {
    $post = BlogPost::factory()->create();

    PublishBlogPost::run($post);

    Notification::assertNothingSentTo(User::factory()->create(), CommunityStoryFeaturedNotification::class);
});

it('does not send story notification when post is only scheduled', function () {
    $question = CommunityQuestion::factory()->active()->create();
    $responseAuthor = User::factory()->create();
    $response = CommunityResponse::factory()->approved()->create([
        'community_question_id' => $question->id,
        'user_id' => $responseAuthor->id,
    ]);

    $reviewer = User::factory()->withRole('Blog Author')->create();
    $post = BlogPost::factory()->inReview()->create([
        'community_question_id' => $question->id,
    ]);
    $post->communityResponses()->sync([$response->id => ['sort_order' => 0]]);

    ApproveBlogPost::run($post, $reviewer, now()->addDays(3));

    Notification::assertNotSentTo($responseAuthor, CommunityStoryFeaturedNotification::class);
});

// === Public Page Rendering ===

it('renders story cards on the public blog post page', function () {
    $response = CommunityResponse::factory()->approved()->create([
        'body' => 'My awesome community story for the blog.',
    ]);

    $post = BlogPost::factory()->published()->create([
        'body' => "Introduction\n\n{{story:{$response->id}}}\n\nConclusion",
    ]);
    $post->communityResponses()->sync([$response->id => ['sort_order' => 0]]);

    $this->get(route('blog.show', $post->slug))
        ->assertOk()
        ->assertSee('My awesome community story for the blog.')
        ->assertSee($response->user->name);
});

it('gracefully handles invalid story markers on the public page', function () {
    $post = BlogPost::factory()->published()->create([
        'body' => "Some content\n\n{{story:99999}}\n\nMore content",
    ]);

    $this->get(route('blog.show', $post->slug))
        ->assertOk()
        ->assertSee('Some content')
        ->assertSee('More content')
        ->assertDontSee('{{story:');
});
