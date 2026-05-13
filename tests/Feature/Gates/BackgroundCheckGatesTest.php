<?php

declare(strict_types=1);

use App\Models\User;

uses()->group('background-checks', 'gates');

// === background-checks-view ===

it('grants background-checks-view to user with Background Checks - View role', function () {
    $user = User::factory()->withRole('Background Checks - View')->create();

    expect($user->can('background-checks-view'))->toBeTrue();
});

it('grants background-checks-view to user with Background Checks - Manage role', function () {
    $user = User::factory()->withRole('Background Checks - Manage')->create();

    expect($user->can('background-checks-view'))->toBeTrue();
});

it('denies background-checks-view to a user with no relevant role', function () {
    $user = User::factory()->create();

    expect($user->can('background-checks-view'))->toBeFalse();
});

it('grants background-checks-view to admin regardless of role', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('background-checks-view'))->toBeTrue();
});

// === background-checks-manage ===

it('grants background-checks-manage to user with Background Checks - Manage role', function () {
    $user = User::factory()->withRole('Background Checks - Manage')->create();

    expect($user->can('background-checks-manage'))->toBeTrue();
});

it('denies background-checks-manage to user with only Background Checks - View role', function () {
    $user = User::factory()->withRole('Background Checks - View')->create();

    expect($user->can('background-checks-manage'))->toBeFalse();
});

it('denies background-checks-manage to a user with no relevant role', function () {
    $user = User::factory()->create();

    expect($user->can('background-checks-manage'))->toBeFalse();
});

it('grants background-checks-manage to admin regardless of role', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('background-checks-manage'))->toBeTrue();
});
