<?php

declare(strict_types=1);

use App\Models\BlogPost;
use App\Models\User;

uses()->group('blog', 'policies');

// === create ===

it('allows blog author to create posts', function () {
    $author = User::factory()->withRole('Blog Author')->create();

    expect($author->can('create', BlogPost::class))->toBeTrue();
});

it('denies regular user from creating posts', function () {
    $user = User::factory()->create();

    expect($user->can('create', BlogPost::class))->toBeFalse();
});

it('allows admin to create posts', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->can('create', BlogPost::class))->toBeTrue();
});

// === update ===

it('allows blog author to update posts', function () {
    $author = User::factory()->withRole('Blog Author')->create();
    $post = BlogPost::factory()->create();

    expect($author->can('update', $post))->toBeTrue();
});

it('denies regular user from updating posts', function () {
    $user = User::factory()->create();
    $post = BlogPost::factory()->create();

    expect($user->can('update', $post))->toBeFalse();
});

it('allows admin to update posts', function () {
    $admin = User::factory()->admin()->create();
    $post = BlogPost::factory()->create();

    expect($admin->can('update', $post))->toBeTrue();
});

// === delete ===

it('allows admin to delete any post', function () {
    $admin = User::factory()->admin()->create();
    $post = BlogPost::factory()->create();

    expect($admin->can('delete', $post))->toBeTrue();
});

it('allows command officer to delete any post', function () {
    $officer = officerCommand();
    $post = BlogPost::factory()->create();

    expect($officer->can('delete', $post))->toBeTrue();
});

it('allows original author with blog author role to delete own post', function () {
    $author = User::factory()->withRole('Blog Author')->create();
    $post = BlogPost::factory()->create(['author_id' => $author->id]);

    expect($author->can('delete', $post))->toBeTrue();
});

it('denies blog author from deleting another authors post', function () {
    $author = User::factory()->withRole('Blog Author')->create();
    $otherAuthor = User::factory()->create();
    $post = BlogPost::factory()->create(['author_id' => $otherAuthor->id]);

    expect($author->can('delete', $post))->toBeFalse();
});

it('denies regular user from deleting posts', function () {
    $user = User::factory()->create();
    $post = BlogPost::factory()->create(['author_id' => $user->id]);

    expect($user->can('delete', $post))->toBeFalse();
});

it('denies non-command officer from deleting other users posts', function () {
    $officer = officerQuartermaster();
    $post = BlogPost::factory()->create();

    expect($officer->can('delete', $post))->toBeFalse();
});
