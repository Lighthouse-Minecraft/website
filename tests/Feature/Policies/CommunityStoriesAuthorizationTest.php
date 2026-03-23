<?php

declare(strict_types=1);

use App\Enums\CommunityResponseStatus;
use App\Models\CommunityResponse;

uses()->group('community-stories', 'policies');

it('allows traveler to view community stories', function () {
    $user = membershipTraveler();

    expect($user->can('view-community-stories'))->toBeTrue();
});

it('denies drifter from viewing community stories', function () {
    $user = membershipDrifter();

    expect($user->can('view-community-stories'))->toBeFalse();
});

it('denies stowaway from viewing community stories', function () {
    $user = membershipStowaway();

    expect($user->can('view-community-stories'))->toBeFalse();
});

it('denies user in brig from viewing community stories', function () {
    $user = membershipTraveler();
    $user->update(['in_brig' => true]);

    expect($user->can('view-community-stories'))->toBeFalse();
});

it('allows citizen to suggest a question', function () {
    $user = membershipCitizen();

    expect($user->can('suggest-community-question'))->toBeTrue();
});

it('denies traveler from suggesting a question', function () {
    $user = membershipTraveler();

    expect($user->can('suggest-community-question'))->toBeFalse();
});

it('allows user with Community Stories - Manager role to manage community stories', function () {
    $user = \App\Models\User::factory()->withRole('Community Stories - Manager')->create();

    expect($user->can('manage-community-stories'))->toBeTrue();
});

it('denies user without Community Stories - Manager role from managing community stories', function () {
    $user = jrCrewQuartermaster();

    expect($user->can('manage-community-stories'))->toBeFalse();
});

it('denies officer without Community Stories - Manager role from managing community stories', function () {
    $user = officerCommand();

    expect($user->can('manage-community-stories'))->toBeFalse();
});

it('allows admin to manage community stories', function () {
    $user = loginAsAdmin();

    expect($user->can('manage-community-stories'))->toBeTrue();
});

it('allows user to edit own unapproved response', function () {
    $user = loginAs(membershipTraveler());
    $response = CommunityResponse::factory()->create([
        'user_id' => $user->id,
        'status' => CommunityResponseStatus::Submitted,
    ]);

    expect($user->can('update', $response))->toBeTrue();
});

it('denies user from editing approved response', function () {
    $user = loginAs(membershipTraveler());
    $response = CommunityResponse::factory()->approved()->create([
        'user_id' => $user->id,
    ]);

    expect($user->can('update', $response))->toBeFalse();
});

it('denies user from editing another users response', function () {
    $user = loginAs(membershipTraveler());
    $response = CommunityResponse::factory()->create();

    expect($user->can('update', $response))->toBeFalse();
});

it('allows admin to edit any unapproved response', function () {
    $admin = loginAsAdmin();
    $response = CommunityResponse::factory()->create();

    expect($admin->can('update', $response))->toBeTrue();
});
