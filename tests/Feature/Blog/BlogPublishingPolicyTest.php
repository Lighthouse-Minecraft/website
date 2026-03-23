<?php

declare(strict_types=1);

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use App\Models\User;

uses()->group('blog', 'policies', 'publishing');

// === submitForReview ===

it('allows post author with blog author role to submit for review', function () {
    $author = User::factory()->withRole('Blog - Author')->create();
    $post = BlogPost::factory()->create(['author_id' => $author->id, 'status' => BlogPostStatus::Draft]);

    expect($author->can('submitForReview', $post))->toBeTrue();
});

it('denies submit for review if user is not the post author', function () {
    $author = User::factory()->withRole('Blog - Author')->create();
    $otherAuthor = User::factory()->withRole('Blog - Author')->create();
    $post = BlogPost::factory()->create(['author_id' => $otherAuthor->id, 'status' => BlogPostStatus::Draft]);

    expect($author->can('submitForReview', $post))->toBeFalse();
});

it('denies submit for review if user does not have blog author role', function () {
    $user = User::factory()->create();
    $post = BlogPost::factory()->create(['author_id' => $user->id, 'status' => BlogPostStatus::Draft]);

    expect($user->can('submitForReview', $post))->toBeFalse();
});

it('denies submit for review if post is not a draft', function () {
    $author = User::factory()->withRole('Blog - Author')->create();
    $post = BlogPost::factory()->inReview()->create(['author_id' => $author->id]);

    expect($author->can('submitForReview', $post))->toBeFalse();
});

it('allows admin to submit any draft for review', function () {
    $admin = User::factory()->admin()->create();
    $post = BlogPost::factory()->create(['status' => BlogPostStatus::Draft]);

    expect($admin->can('submitForReview', $post))->toBeTrue();
});

// === approve ===

it('allows a different blog author to approve a post in review', function () {
    $author = User::factory()->create();
    $reviewer = User::factory()->withRole('Blog - Author')->create();
    $post = BlogPost::factory()->inReview()->create(['author_id' => $author->id]);

    expect($reviewer->can('approve', $post))->toBeTrue();
});

it('denies post author from approving their own post', function () {
    $author = User::factory()->withRole('Blog - Author')->create();
    $post = BlogPost::factory()->inReview()->create(['author_id' => $author->id]);

    expect($author->can('approve', $post))->toBeFalse();
});

it('denies approval if post is not in review', function () {
    $reviewer = User::factory()->withRole('Blog - Author')->create();
    $post = BlogPost::factory()->create(['status' => BlogPostStatus::Draft]);

    expect($reviewer->can('approve', $post))->toBeFalse();
});

it('denies approval if user does not have blog author role', function () {
    $user = User::factory()->create();
    $author = User::factory()->create();
    $post = BlogPost::factory()->inReview()->create(['author_id' => $author->id]);

    expect($user->can('approve', $post))->toBeFalse();
});

it('allows admin to approve any post in review', function () {
    $admin = User::factory()->admin()->create();
    $post = BlogPost::factory()->inReview()->create();

    expect($admin->can('approve', $post))->toBeTrue();
});

// === archive ===

it('allows blog author to archive a published post', function () {
    $author = User::factory()->withRole('Blog - Author')->create();
    $post = BlogPost::factory()->published()->create();

    expect($author->can('archive', $post))->toBeTrue();
});

it('denies archiving a non-published post', function () {
    $author = User::factory()->withRole('Blog - Author')->create();
    $post = BlogPost::factory()->create(['status' => BlogPostStatus::Draft]);

    expect($author->can('archive', $post))->toBeFalse();
});

it('denies archiving if user does not have blog author role', function () {
    $user = User::factory()->create();
    $post = BlogPost::factory()->published()->create();

    expect($user->can('archive', $post))->toBeFalse();
});

it('allows admin to archive a published post', function () {
    $admin = User::factory()->admin()->create();
    $post = BlogPost::factory()->published()->create();

    expect($admin->can('archive', $post))->toBeTrue();
});
