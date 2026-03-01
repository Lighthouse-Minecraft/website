<?php

declare(strict_types=1);

use App\Models\ParentChildLink;
use App\Models\User;

uses()->group('parent-portal', 'gates');

it('allows adult to view parent portal', function () {
    $user = User::factory()->adult()->create();

    expect($user->can('view-parent-portal'))->toBeTrue();
});

it('allows user with children to view parent portal', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    expect($parent->can('view-parent-portal'))->toBeTrue();
});

it('denies minor without children', function () {
    $user = User::factory()->minor()->create();

    expect($user->can('view-parent-portal'))->toBeFalse();
});

it('blocks MC linking when parent_allows_minecraft is false', function () {
    $user = User::factory()->create(['parent_allows_minecraft' => false]);

    expect($user->can('link-minecraft-account'))->toBeFalse();
});

it('allows MC linking when parent_allows_minecraft is true and not in brig', function () {
    $user = User::factory()->create(['parent_allows_minecraft' => true, 'in_brig' => false]);

    expect($user->can('link-minecraft-account'))->toBeTrue();
});

it('blocks discord linking when parent_allows_discord is false', function () {
    $user = User::factory()->withMembershipLevel(\App\Enums\MembershipLevel::Traveler)->create([
        'parent_allows_discord' => false,
    ]);

    expect($user->can('link-discord'))->toBeFalse();
});
