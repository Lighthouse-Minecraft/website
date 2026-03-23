<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\User;

uses()->group('policies', 'pages', 'roles');

// == viewAny == //

it('grants page viewAny to Page - Editor', function () {
    $user = User::factory()->withRole('Page - Editor')->create();

    expect($user->can('viewAny', Page::class))->toBeTrue();
});

it('denies page viewAny without Page - Editor role', function () {
    $user = User::factory()->withRole('Staff Access')->create();

    expect($user->can('viewAny', Page::class))->toBeFalse();
});

it('grants page viewAny to admin', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('viewAny', Page::class))->toBeTrue();
});

// == create == //

it('grants page create to Page - Editor', function () {
    $user = User::factory()->withRole('Page - Editor')->create();

    expect($user->can('create', Page::class))->toBeTrue();
});

it('denies page create without Page - Editor role', function () {
    $user = User::factory()
        ->withStaffPosition(\App\Enums\StaffDepartment::Steward, \App\Enums\StaffRank::Officer)
        ->create();

    expect($user->can('create', Page::class))->toBeFalse();
});

// == update == //

it('grants page update to Page - Editor', function () {
    $user = User::factory()->withRole('Page - Editor')->create();
    $page = Page::create(['title' => 'Test', 'slug' => 'test-'.uniqid(), 'content' => 'Content', 'is_published' => true]);

    expect($user->can('update', $page))->toBeTrue();
});

it('denies page update without Page - Editor role', function () {
    $user = User::factory()->withRole('Staff Access')->create();
    $page = Page::create(['title' => 'Test', 'slug' => 'test-'.uniqid(), 'content' => 'Content', 'is_published' => true]);

    expect($user->can('update', $page))->toBeFalse();
});

// == delete == //

it('grants page delete to Page - Editor', function () {
    $user = User::factory()->withRole('Page - Editor')->create();
    $page = Page::create(['title' => 'Test', 'slug' => 'test-'.uniqid(), 'content' => 'Content', 'is_published' => true]);

    expect($user->can('delete', $page))->toBeTrue();
});

it('denies page delete without Page - Editor role', function () {
    $user = User::factory()->withRole('Staff Access')->create();
    $page = Page::create(['title' => 'Test', 'slug' => 'test-'.uniqid(), 'content' => 'Content', 'is_published' => true]);

    expect($user->can('delete', $page))->toBeFalse();
});

// == view == //

it('anyone can view published pages', function () {
    $user = User::factory()->create();
    $page = Page::create(['title' => 'Published', 'slug' => 'pub-'.uniqid(), 'content' => 'Content', 'is_published' => true]);

    expect($user->can('view', $page))->toBeTrue();
});

it('Page - Editor can view unpublished pages', function () {
    $user = User::factory()->withRole('Page - Editor')->create();
    $page = Page::create(['title' => 'Draft', 'slug' => 'draft-'.uniqid(), 'content' => 'Content', 'is_published' => false]);

    expect($user->can('view', $page))->toBeTrue();
});

it('non-editor cannot view unpublished pages', function () {
    $user = User::factory()->create();
    $page = Page::create(['title' => 'Draft', 'slug' => 'draft2-'.uniqid(), 'content' => 'Content', 'is_published' => false]);

    expect($user->can('view', $page))->toBeFalse();
});
