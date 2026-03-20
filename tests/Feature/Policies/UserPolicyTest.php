<?php

declare(strict_types=1);

use App\Models\User;
use App\Policies\UserPolicy;

uses()->group('policies');

// === before() bypass ===

it('admin can bypass all policy checks', function () {
    $admin = loginAsAdmin();
    $target = User::factory()->create();
    $policy = new UserPolicy;

    expect($policy->before($admin, 'view'))->toBeTrue();
});

it('command officer no longer bypasses policy before hook', function () {
    $officer = officerCommand();
    $policy = new UserPolicy;

    expect($policy->before($officer, 'view'))->toBeNull();
});

it('non-admin non-command returns null from before', function () {
    $user = User::factory()->create();
    $policy = new UserPolicy;

    expect($policy->before($user, 'view'))->toBeNull();
});

// === viewAny ===

it('admin can view any users', function () {
    $admin = loginAsAdmin();
    expect($admin->can('viewAny', User::class))->toBeTrue();
});

it('user with User Manager role can view any users', function () {
    $user = User::factory()->withRole('User Manager')->create();
    expect($user->can('viewAny', User::class))->toBeTrue();
});

it('regular user cannot view any users', function () {
    $user = membershipTraveler();
    expect($user->can('viewAny', User::class))->toBeFalse();
});

// === view ===

it('user can view their own profile', function () {
    $user = User::factory()->create();
    expect($user->can('view', $user))->toBeTrue();
});

it('traveler can view other profiles', function () {
    $viewer = membershipTraveler();
    $target = User::factory()->create();
    expect($viewer->can('view', $target))->toBeTrue();
});

it('drifter can view other profiles', function () {
    $viewer = membershipDrifter();
    $target = User::factory()->create();

    expect($viewer->can('view', $target))->toBeTrue();
});

it('stowaway can view other profiles', function () {
    $viewer = membershipStowaway();
    $target = User::factory()->create();

    expect($viewer->can('view', $target))->toBeTrue();
});

// === viewPii ===

it('admin can view PII', function () {
    $admin = loginAsAdmin();
    $target = User::factory()->create();
    expect($admin->can('viewPii', $target))->toBeTrue();
});

it('user with View PII role can view PII of others', function () {
    $user = User::factory()->withRole('View PII')->create();
    $target = User::factory()->create();
    expect($user->can('viewPii', $target))->toBeTrue();
});

it('user can view their own PII', function () {
    $user = membershipTraveler();
    expect($user->can('viewPii', $user))->toBeTrue();
});

it('regular user cannot view PII of others', function () {
    $user = membershipTraveler();
    $target = User::factory()->create();
    expect($user->can('viewPii', $target))->toBeFalse();
});

// === update ===

it('user can update themselves', function () {
    $user = User::factory()->create();
    expect($user->can('update', $user))->toBeTrue();
});

it('user with User Manager role can update other users', function () {
    $user = User::factory()->withRole('User Manager')->create();
    $target = User::factory()->create();
    expect($user->can('update', $target))->toBeTrue();
});

it('officer without User Manager role cannot update other users', function () {
    $officer = officerQuartermaster();
    $target = User::factory()->create();
    expect($officer->can('update', $target))->toBeFalse();
});

// === create / delete / restore / forceDelete ===

it('no one can create users through policy', function () {
    $admin = loginAsAdmin();
    $policy = new UserPolicy;
    // Admin bypasses via before(), so test the raw method
    $user = User::factory()->create();
    expect($policy->create($user))->toBeFalse();
});

it('no one can delete users through policy', function () {
    $policy = new UserPolicy;
    $user = User::factory()->create();
    $target = User::factory()->create();
    expect($policy->delete($user, $target))->toBeFalse();
});

it('no one can restore users through policy', function () {
    $policy = new UserPolicy;
    $user = User::factory()->create();
    $target = User::factory()->create();
    expect($policy->restore($user, $target))->toBeFalse();
});

it('no one can force delete users through policy', function () {
    $policy = new UserPolicy;
    $user = User::factory()->create();
    $target = User::factory()->create();
    expect($policy->forceDelete($user, $target))->toBeFalse();
});

// === viewStaffPhone ===

it('admin can view staff phone of any user', function () {
    $admin = loginAsAdmin();
    $target = User::factory()->create();
    expect($admin->can('viewStaffPhone', $target))->toBeTrue();
});

it('officer can view staff phone of staff target', function () {
    $officer = officerQuartermaster();
    $target = crewQuartermaster();
    expect($officer->can('viewStaffPhone', $target))->toBeTrue();
});

it('officer cannot view staff phone of non-staff target', function () {
    $officer = officerQuartermaster();
    $target = User::factory()->create();
    expect($officer->can('viewStaffPhone', $target))->toBeFalse();
});

it('board member can view staff phone of staff target', function () {
    $boardMember = User::factory()->create(['is_board_member' => true]);
    $target = User::factory()->create(['is_board_member' => true]);
    expect($boardMember->can('viewStaffPhone', $target))->toBeTrue();
});

it('board member cannot view staff phone of non-staff target', function () {
    $boardMember = User::factory()->create(['is_board_member' => true]);
    $target = User::factory()->create();
    expect($boardMember->can('viewStaffPhone', $target))->toBeFalse();
});

it('regular user cannot view staff phone', function () {
    $user = membershipTraveler();
    $target = crewQuartermaster();
    expect($user->can('viewStaffPhone', $target))->toBeFalse();
});

it('crew member cannot view staff phone', function () {
    $crew = crewQuartermaster();
    $target = crewQuartermaster();
    expect($crew->can('viewStaffPhone', $target))->toBeFalse();
});

// === updateStaffPosition / removeStaffPosition ===

it('no one can update staff positions through policy (requires admin bypass)', function () {
    $policy = new UserPolicy;
    $user = User::factory()->create();
    $target = User::factory()->create();
    expect($policy->updateStaffPosition($user, $target))->toBeFalse();
});

it('no one can remove staff positions through policy (requires admin bypass)', function () {
    $policy = new UserPolicy;
    $user = User::factory()->create();
    $target = User::factory()->create();
    expect($policy->removeStaffPosition($user, $target))->toBeFalse();
});
