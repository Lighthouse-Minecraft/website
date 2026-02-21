<?php

declare(strict_types=1);

use App\Enums\MembershipLevel;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\User;
use App\Policies\UserPolicy;

uses()->group('policies');

// === before() bypass ===

it('admin can bypass all policy checks', function () {
    $admin = loginAsAdmin();
    $target = User::factory()->create();
    $policy = new UserPolicy();

    expect($policy->before($admin, 'view'))->toBeTrue();
});

it('command officer can bypass all policy checks', function () {
    $officer = officerCommand();
    $target = User::factory()->create();
    $policy = new UserPolicy();

    expect($policy->before($officer, 'view'))->toBeTrue();
});

it('non-admin non-command returns null from before', function () {
    $user = User::factory()->create();
    $policy = new UserPolicy();

    expect($policy->before($user, 'view'))->toBeNull();
});

// === viewAny ===

it('admin can view any users', function () {
    $admin = loginAsAdmin();
    expect($admin->can('viewAny', User::class))->toBeTrue();
});

it('quartermaster officer can view any users', function () {
    $officer = officerQuartermaster();
    expect($officer->can('viewAny', User::class))->toBeTrue();
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

it('drifter cannot view other profiles', function () {
    $viewer = membershipDrifter();
    $target = User::factory()->create();

    // Admin bypass will not apply here; drifter is not admin
    // Drifters are below Traveler level so view() returns false for others
    expect($viewer->can('view', $target))->toBeFalse();
});

// === viewPii ===

it('admin can view PII', function () {
    $admin = loginAsAdmin();
    $target = User::factory()->create();
    expect($admin->can('viewPii', $target))->toBeTrue();
});

it('command staff can view PII', function () {
    $staff = crewCommand();
    $target = User::factory()->create();
    expect($staff->can('viewPii', $target))->toBeTrue();
});

it('quartermaster staff can view PII', function () {
    $staff = crewQuartermaster();
    $target = User::factory()->create();
    expect($staff->can('viewPii', $target))->toBeTrue();
});

it('regular user cannot view PII', function () {
    $user = membershipTraveler();
    $target = User::factory()->create();
    expect($user->can('viewPii', $target))->toBeFalse();
});

// === update ===

it('user can update themselves', function () {
    $user = User::factory()->create();
    expect($user->can('update', $user))->toBeTrue();
});

it('quartermaster officer can update other users', function () {
    $officer = officerQuartermaster();
    $target = User::factory()->create();
    expect($officer->can('update', $target))->toBeTrue();
});

it('regular crew member cannot update other users', function () {
    $crew = crewQuartermaster();
    $target = User::factory()->create();
    expect($crew->can('update', $target))->toBeFalse();
});

// === create / delete / restore / forceDelete ===

it('no one can create users through policy', function () {
    $admin = loginAsAdmin();
    $policy = new UserPolicy();
    // Admin bypasses via before(), so test the raw method
    $user = User::factory()->create();
    expect($policy->create($user))->toBeFalse();
});

it('no one can delete users through policy', function () {
    $policy = new UserPolicy();
    $user = User::factory()->create();
    $target = User::factory()->create();
    expect($policy->delete($user, $target))->toBeFalse();
});

it('no one can restore users through policy', function () {
    $policy = new UserPolicy();
    $user = User::factory()->create();
    $target = User::factory()->create();
    expect($policy->restore($user, $target))->toBeFalse();
});

it('no one can force delete users through policy', function () {
    $policy = new UserPolicy();
    $user = User::factory()->create();
    $target = User::factory()->create();
    expect($policy->forceDelete($user, $target))->toBeFalse();
});

it('no one can update staff positions through policy (requires admin bypass)', function () {
    $policy = new UserPolicy();
    $user = User::factory()->create();
    $target = User::factory()->create();
    expect($policy->updateStaffPosition($user, $target))->toBeFalse();
});

it('no one can remove staff positions through policy (requires admin bypass)', function () {
    $policy = new UserPolicy();
    $user = User::factory()->create();
    $target = User::factory()->create();
    expect($policy->removeStaffPosition($user, $target))->toBeFalse();
});
