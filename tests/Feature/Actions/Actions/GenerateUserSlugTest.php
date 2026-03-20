<?php

declare(strict_types=1);

use App\Actions\GenerateUserSlug;
use App\Models\User;

uses()->group('user-slug', 'actions');

it('generates a slug from a display name', function () {
    $slug = GenerateUserSlug::run('Jane Doe');

    expect($slug)->toBe('jane-doe');
});

it('generates a slug with numeric suffix on collision', function () {
    User::factory()->create(['name' => 'Jane Doe']);

    $slug = GenerateUserSlug::run('Jane Doe');

    expect($slug)->toBe('jane-doe-2');
});

it('increments suffix for multiple collisions', function () {
    User::factory()->create(['name' => 'Jane Doe']);
    User::factory()->create(['name' => 'Jane Doe']);

    $slug = GenerateUserSlug::run('Jane Doe');

    expect($slug)->toBe('jane-doe-3');
});

it('excludes the given user id from collision check', function () {
    $user = User::factory()->create(['name' => 'Jane Doe']);

    $slug = GenerateUserSlug::run('Jane Doe', $user->id);

    expect($slug)->toBe('jane-doe');
});

it('falls back to user when name produces empty slug', function () {
    $slug = GenerateUserSlug::run('!!!');

    expect($slug)->toBe('user');
});

it('auto-generates slug on user creation', function () {
    $user = User::factory()->create(['name' => 'John Smith']);

    expect($user->slug)->toBe('john-smith');
});

it('updates slug when display name changes', function () {
    $user = User::factory()->create(['name' => 'John Smith']);

    $user->name = 'Jane Smith';
    $user->save();

    expect($user->fresh()->slug)->toBe('jane-smith');
});

it('handles collision on display name update', function () {
    User::factory()->create(['name' => 'Jane Smith']);
    $user = User::factory()->create(['name' => 'John Smith']);

    $user->name = 'Jane Smith';
    $user->save();

    expect($user->fresh()->slug)->toBe('jane-smith-2');
});
