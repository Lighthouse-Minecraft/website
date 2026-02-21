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

// === view ===

it('view always returns false through policy', function () {
    $policy = new MinecraftAccountPolicy;
    $user = User::factory()->create();
    $account = MinecraftAccount::factory()->create(['user_id' => $user->id]);

    expect($policy->view($user, $account))->toBeFalse();
});

// === create ===

it('create always returns false through policy', function () {
    $policy = new MinecraftAccountPolicy;
    $user = User::factory()->create();

    expect($policy->create($user))->toBeFalse();
});

// === update ===

it('update always returns false through policy', function () {
    $policy = new MinecraftAccountPolicy;
    $user = User::factory()->create();
    $account = MinecraftAccount::factory()->create(['user_id' => $user->id]);

    expect($policy->update($user, $account))->toBeFalse();
});

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

// === restore ===

it('restore always returns false through policy', function () {
    $policy = new MinecraftAccountPolicy;
    $user = User::factory()->create();
    $account = MinecraftAccount::factory()->create(['user_id' => $user->id]);

    expect($policy->restore($user, $account))->toBeFalse();
});

// === forceDelete ===

it('forceDelete always returns false through policy', function () {
    $policy = new MinecraftAccountPolicy;
    $user = User::factory()->create();
    $account = MinecraftAccount::factory()->create(['user_id' => $user->id]);

    expect($policy->forceDelete($user, $account))->toBeFalse();
});
