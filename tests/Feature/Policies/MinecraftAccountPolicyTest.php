<?php

declare(strict_types=1);

use App\Models\MinecraftAccount;
use App\Models\User;
use App\Policies\MinecraftAccountPolicy;

uses()->group('policies');

// === viewAny ===

it('admin can view any minecraft accounts', function () {
    $admin = loginAsAdmin();
    expect($admin->can('viewAny', MinecraftAccount::class))->toBeTrue();
});

it('regular user cannot view any minecraft accounts', function () {
    $user = membershipTraveler();
    expect($user->can('viewAny', MinecraftAccount::class))->toBeFalse();
});

// === view / create / update / restore always return false ===

it('always returns false through policy', function (string $action, bool $needsAccount) {
    $policy = new MinecraftAccountPolicy;
    $user = User::factory()->create();
    $account = $needsAccount ? MinecraftAccount::factory()->create(['user_id' => $user->id]) : null;

    $result = $needsAccount ? $policy->$action($user, $account) : $policy->$action($user);

    expect($result)->toBeFalse();
})->with([
    'view' => ['view', true],
    'create' => ['create', false],
    'update' => ['update', true],
    'restore' => ['restore', true],
]);

// === delete ===

it('user can delete their own minecraft account', function () {
    $user = User::factory()->create();
    $account = MinecraftAccount::factory()->create(['user_id' => $user->id]);

    expect($user->can('delete', $account))->toBeTrue();
});

it('admin can delete any minecraft account', function () {
    $admin = loginAsAdmin();
    $owner = User::factory()->create();
    $account = MinecraftAccount::factory()->create(['user_id' => $owner->id]);

    expect($admin->can('delete', $account))->toBeTrue();
});

it('other user cannot delete someone elses minecraft account', function () {
    $user = membershipTraveler();
    $owner = User::factory()->create();
    $account = MinecraftAccount::factory()->create(['user_id' => $owner->id]);

    expect($user->can('delete', $account))->toBeFalse();
});

// === reactivate ===

it('user can reactivate their own minecraft account', function () {
    $user = User::factory()->create();
    $account = MinecraftAccount::factory()->removed()->create(['user_id' => $user->id]);

    expect($user->can('reactivate', $account))->toBeTrue();
});

it('admin can reactivate any minecraft account', function () {
    $admin = loginAsAdmin();
    $owner = User::factory()->create();
    $account = MinecraftAccount::factory()->removed()->create(['user_id' => $owner->id]);

    expect($admin->can('reactivate', $account))->toBeTrue();
});

it('other user cannot reactivate someone elses minecraft account', function () {
    $user = membershipTraveler();
    $owner = User::factory()->create();
    $account = MinecraftAccount::factory()->removed()->create(['user_id' => $owner->id]);

    expect($user->can('reactivate', $account))->toBeFalse();
});

// === forceDelete ===

it('admin can force delete a minecraft account', function () {
    $admin = loginAsAdmin();
    $owner = User::factory()->create();
    $account = MinecraftAccount::factory()->removed()->create(['user_id' => $owner->id]);

    expect($admin->can('forceDelete', $account))->toBeTrue();
});

it('regular user cannot force delete a minecraft account', function () {
    $user = membershipTraveler();
    $account = MinecraftAccount::factory()->removed()->create(['user_id' => $user->id]);

    expect($user->can('forceDelete', $account))->toBeFalse();
});

// === revoke ===

it('admin can revoke a minecraft account', function () {
    $admin = loginAsAdmin();
    $owner = User::factory()->create();
    $account = MinecraftAccount::factory()->active()->create(['user_id' => $owner->id]);

    expect($admin->can('revoke', $account))->toBeTrue();
});

it('engineer officer can revoke a minecraft account', function () {
    $officer = officerEngineer();
    $owner = User::factory()->create();
    $account = MinecraftAccount::factory()->active()->create(['user_id' => $owner->id]);

    expect($officer->can('revoke', $account))->toBeTrue();
});

it('command officer can revoke a minecraft account', function () {
    $officer = officerCommand();
    $owner = User::factory()->create();
    $account = MinecraftAccount::factory()->active()->create(['user_id' => $owner->id]);

    expect($officer->can('revoke', $account))->toBeTrue();
});

it('steward officer cannot revoke a minecraft account', function () {
    $officer = officerSteward();
    $owner = User::factory()->create();
    $account = MinecraftAccount::factory()->active()->create(['user_id' => $owner->id]);

    expect($officer->can('revoke', $account))->toBeFalse();
});

it('engineer crew member cannot revoke a minecraft account', function () {
    $crew = crewEngineer();
    $owner = User::factory()->create();
    $account = MinecraftAccount::factory()->active()->create(['user_id' => $owner->id]);

    expect($crew->can('revoke', $account))->toBeFalse();
});

it('regular user cannot revoke a minecraft account', function () {
    $user = membershipTraveler();
    $owner = User::factory()->create();
    $account = MinecraftAccount::factory()->active()->create(['user_id' => $owner->id]);

    expect($user->can('revoke', $account))->toBeFalse();
});

// === viewUuid ===

it('admin can view uuid', function () {
    $admin = loginAsAdmin();
    $account = MinecraftAccount::factory()->create();

    expect($admin->can('viewUuid', $account))->toBeTrue();
});

it('engineer crew member can view uuid', function () {
    $crew = crewEngineer();
    $account = MinecraftAccount::factory()->create();

    expect($crew->can('viewUuid', $account))->toBeTrue();
});

it('officer in any department can view uuid', function () {
    $officer = officerSteward();
    $account = MinecraftAccount::factory()->create();

    expect($officer->can('viewUuid', $account))->toBeTrue();
});

it('regular user cannot view uuid', function () {
    $user = membershipTraveler();
    $account = MinecraftAccount::factory()->create();

    expect($user->can('viewUuid', $account))->toBeFalse();
});

it('non-engineer crew member cannot view uuid', function () {
    $crew = crewSteward();
    $account = MinecraftAccount::factory()->create();

    expect($crew->can('viewUuid', $account))->toBeFalse();
});

// === viewStaffAuditFields ===

it('admin can view staff audit fields', function () {
    $admin = loginAsAdmin();
    $account = MinecraftAccount::factory()->create();

    expect($admin->can('viewStaffAuditFields', $account))->toBeTrue();
});

it('staff member can view staff audit fields', function () {
    $crew = crewSteward();
    $account = MinecraftAccount::factory()->create();

    expect($crew->can('viewStaffAuditFields', $account))->toBeTrue();
});

it('regular user cannot view staff audit fields', function () {
    $user = membershipTraveler();
    $account = MinecraftAccount::factory()->create();

    expect($user->can('viewStaffAuditFields', $account))->toBeFalse();
});
