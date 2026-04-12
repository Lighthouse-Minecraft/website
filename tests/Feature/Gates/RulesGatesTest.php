<?php

declare(strict_types=1);

use App\Models\User;

uses()->group('rules', 'gates');

// == rules.manage == //

it('grants rules.manage to user with Rules - Manage role', function () {
    $user = User::factory()->withRole('Rules - Manage')->create();

    expect($user->can('rules.manage'))->toBeTrue();
});

it('denies rules.manage to user with only Rules - Approve role', function () {
    $user = User::factory()->withRole('Rules - Approve')->create();

    expect($user->can('rules.manage'))->toBeFalse();
});

it('denies rules.manage to user with no rules role', function () {
    $user = User::factory()->create();

    expect($user->can('rules.manage'))->toBeFalse();
});

// == rules.approve == //

it('grants rules.approve to user with Rules - Approve role', function () {
    $user = User::factory()->withRole('Rules - Approve')->create();

    expect($user->can('rules.approve'))->toBeTrue();
});

it('denies rules.approve to user with only Rules - Manage role', function () {
    $user = User::factory()->withRole('Rules - Manage')->create();

    expect($user->can('rules.approve'))->toBeFalse();
});

it('denies rules.approve to user with no rules role', function () {
    $user = User::factory()->create();

    expect($user->can('rules.approve'))->toBeFalse();
});
