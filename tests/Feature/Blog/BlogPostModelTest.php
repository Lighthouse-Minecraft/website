<?php

declare(strict_types=1);

use App\Enums\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;

uses()->group('blog', 'models');

it('creates a blog post with factory defaults', function () {
    $post = BlogPost::factory()->create();

    expect($post->status)->toBe(BlogPostStatus::Draft)
        ->and($post->is_edited)->toBeFalse()
        ->and($post->author)->toBeInstanceOf(User::class);
});

it('creates a published blog post', function () {
    $post = BlogPost::factory()->published()->create();

    expect($post->status)->toBe(BlogPostStatus::Published)
        ->and($post->published_at)->not->toBeNull();
});

it('creates a blog post with a category', function () {
    $post = BlogPost::factory()->withCategory()->create();

    expect($post->category)->toBeInstanceOf(BlogCategory::class);
});

it('attaches tags to a blog post', function () {
    $post = BlogPost::factory()->create();
    $tags = BlogTag::factory()->count(3)->create();

    $post->tags()->sync($tags->pluck('id'));

    expect($post->fresh()->tags)->toHaveCount(3);
});

it('soft deletes a blog post', function () {
    $post = BlogPost::factory()->create();

    $post->delete();

    expect(BlogPost::find($post->id))->toBeNull()
        ->and(BlogPost::withTrashed()->find($post->id))->not->toBeNull();
});

it('creates blog categories with factory', function () {
    $category = BlogCategory::factory()->create();

    expect($category->name)->not->toBeEmpty()
        ->and($category->slug)->not->toBeEmpty()
        ->and($category->include_in_sitemap)->toBeTrue();
});

it('creates blog tags with factory', function () {
    $tag = BlogTag::factory()->create();

    expect($tag->name)->not->toBeEmpty()
        ->and($tag->slug)->not->toBeEmpty()
        ->and($tag->include_in_sitemap)->toBeFalse();
});

it('has correct status enum values', function () {
    expect(BlogPostStatus::Draft->value)->toBe('draft')
        ->and(BlogPostStatus::InReview->value)->toBe('in_review')
        ->and(BlogPostStatus::Scheduled->value)->toBe('scheduled')
        ->and(BlogPostStatus::Published->value)->toBe('published')
        ->and(BlogPostStatus::Archived->value)->toBe('archived');
});

it('casts status to enum', function () {
    $post = BlogPost::factory()->create(['status' => 'in_review']);

    expect($post->status)->toBe(BlogPostStatus::InReview);
});

it('returns user blog posts via relationship', function () {
    $user = User::factory()->create();
    BlogPost::factory()->count(2)->create(['author_id' => $user->id]);

    expect($user->blogPosts)->toHaveCount(2);
});
