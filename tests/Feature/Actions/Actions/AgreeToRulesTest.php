<?php

declare(strict_types=1);

use App\Actions\AgreeToRules;
use App\Enums\MembershipLevel;
use App\Models\User;

uses()->group('actions');

it('self-agreement sets rules_accepted_at and rules_accepted_by_user_id to the user own id', function () {
    $user = User::factory()->withMembershipLevel(MembershipLevel::Drifter)->create();

    $result = AgreeToRules::run($user, $user);

    expect($result['success'])->toBeTrue();

    $fresh = $user->fresh();
    expect($fresh->rules_accepted_at)->not->toBeNull()
        ->and($fresh->rules_accepted_by_user_id)->toBe($user->id);
});

it('self-agreement promotes the user to Stowaway', function () {
    $user = User::factory()->withMembershipLevel(MembershipLevel::Drifter)->create();

    AgreeToRules::run($user, $user);

    expect($user->fresh()->membership_level)->toBe(MembershipLevel::Stowaway);
});

it('self-agreement logs a rules_accepted activity', function () {
    $user = User::factory()->withMembershipLevel(MembershipLevel::Drifter)->create();

    AgreeToRules::run($user, $user);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'action' => 'rules_accepted',
    ]);
});

it('parent-agreement sets rules_accepted_by_user_id to the parent id', function () {
    $parent = User::factory()->create();
    $child = User::factory()->withMembershipLevel(MembershipLevel::Drifter)->create();

    $result = AgreeToRules::run($child, $parent);

    expect($result['success'])->toBeTrue();

    $fresh = $child->fresh();
    expect($fresh->rules_accepted_by_user_id)->toBe($parent->id)
        ->and($fresh->rules_accepted_by_user_id)->not->toBe($child->id);
});

it('parent-agreement promotes the child to Stowaway', function () {
    $parent = User::factory()->create();
    $child = User::factory()->withMembershipLevel(MembershipLevel::Drifter)->create();

    AgreeToRules::run($child, $parent);

    expect($child->fresh()->membership_level)->toBe(MembershipLevel::Stowaway);
});

it('guard rejects agreement for an already-Stowaway user', function () {
    $user = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create([
        'rules_accepted_by_user_id' => null,
    ]);

    $result = AgreeToRules::run($user, $user);

    expect($result['success'])->toBeFalse();

    // rules_accepted_by_user_id was not changed
    expect($user->fresh()->rules_accepted_by_user_id)->toBeNull();
});

it('guard rejects agreement for a user above Stowaway', function () {
    $user = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create([
        'rules_accepted_by_user_id' => null,
    ]);

    $result = AgreeToRules::run($user, $user);

    expect($result['success'])->toBeFalse();
    expect($user->fresh()->rules_accepted_by_user_id)->toBeNull();
});
