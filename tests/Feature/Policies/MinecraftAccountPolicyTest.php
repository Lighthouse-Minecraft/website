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

// === view / create / update / restore / forceDelete always return false ===

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
    'forceDelete' => ['forceDelete', true],
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
