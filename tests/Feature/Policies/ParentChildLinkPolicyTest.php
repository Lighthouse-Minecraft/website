<?php

declare(strict_types=1);

use App\Models\ParentChildLink;
use App\Models\User;
use App\Policies\ParentChildLinkPolicy;

uses()->group('parent-portal', 'policies');

it('allows parent to manage their child', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();
    ParentChildLink::factory()->create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    $policy = new ParentChildLinkPolicy;
    expect($policy->manage($parent, $child))->toBeTrue();
});

it('denies non-parent from managing a child', function () {
    $stranger = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();

    $policy = new ParentChildLinkPolicy;
    expect($policy->manage($stranger, $child))->toBeFalse();
});

it('denies parent from managing a different child', function () {
    $parent = User::factory()->adult()->create();
    $theirChild = User::factory()->minor()->create();
    $otherChild = User::factory()->minor()->create();
    ParentChildLink::factory()->create(['parent_user_id' => $parent->id, 'child_user_id' => $theirChild->id]);

    $policy = new ParentChildLinkPolicy;
    expect($policy->manage($parent, $otherChild))->toBeFalse();
});
