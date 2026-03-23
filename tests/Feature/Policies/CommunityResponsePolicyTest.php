<?php

declare(strict_types=1);

use App\Models\CommunityResponse;
use App\Models\User;
use App\Policies\CommunityResponsePolicy;

uses()->group('policies', 'community-stories');

// === before() hook ===

it('admin bypasses community response policy via before hook', function () {
    $admin = User::factory()->admin()->create();
    $policy = new CommunityResponsePolicy;

    expect($policy->before($admin, 'view'))->toBeTrue();
});

it('non-admin returns null from community response policy before hook', function () {
    $user = User::factory()->create();
    $policy = new CommunityResponsePolicy;

    expect($policy->before($user, 'view'))->toBeNull();
});

it('command officer returns null from community response policy before hook', function () {
    $officer = officerCommand();
    $policy = new CommunityResponsePolicy;

    expect($policy->before($officer, 'view'))->toBeNull();
});

// === view ===

it('user with Community Stories - Manager role can view any response', function () {
    $manager = User::factory()->withRole('Community Stories - Manager')->create();
    $response = CommunityResponse::factory()->create();

    expect($manager->can('view', $response))->toBeTrue();
});

it('user can view their own response', function () {
    $user = User::factory()->create();
    $response = CommunityResponse::factory()->create(['user_id' => $user->id]);

    expect($user->can('view', $response))->toBeTrue();
});

it('user can view approved responses', function () {
    $user = User::factory()->create();
    $response = CommunityResponse::factory()->approved()->create();

    expect($user->can('view', $response))->toBeTrue();
});

// === update ===

it('user with Community Stories - Manager role can update any response', function () {
    $manager = User::factory()->withRole('Community Stories - Manager')->create();
    $response = CommunityResponse::factory()->create();

    expect($manager->can('update', $response))->toBeTrue();
});

it('owner can update their own editable response', function () {
    $user = User::factory()->create();
    $response = CommunityResponse::factory()->create(['user_id' => $user->id]);

    expect($user->can('update', $response))->toBeTrue();
});

it('non-owner without role cannot update response', function () {
    $user = User::factory()->create();
    $response = CommunityResponse::factory()->create();

    expect($user->can('update', $response))->toBeFalse();
});

// === delete ===

it('user with Community Stories - Manager role can delete any response', function () {
    $manager = User::factory()->withRole('Community Stories - Manager')->create();
    $response = CommunityResponse::factory()->create();

    expect($manager->can('delete', $response))->toBeTrue();
});

it('owner can delete their own editable response', function () {
    $user = User::factory()->create();
    $response = CommunityResponse::factory()->create(['user_id' => $user->id]);

    expect($user->can('delete', $response))->toBeTrue();
});

it('non-owner without role cannot delete response', function () {
    $user = User::factory()->create();
    $response = CommunityResponse::factory()->create();

    expect($user->can('delete', $response))->toBeFalse();
});
