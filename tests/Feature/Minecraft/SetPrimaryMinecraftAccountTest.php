<?php

declare(strict_types=1);

use App\Actions\SetPrimaryMinecraftAccount;
use App\Models\MinecraftAccount;
use App\Models\User;

test('sets an active account as primary', function () {
    $user = User::factory()->create();
    $account = MinecraftAccount::factory()->active()->for($user)->create();

    $result = SetPrimaryMinecraftAccount::run($account);

    expect($result)->toBeTrue()
        ->and($account->fresh()->is_primary)->toBeTrue();
});

test('clears primary from other accounts when setting new primary', function () {
    $user = User::factory()->create();
    $old = MinecraftAccount::factory()->active()->primary()->for($user)->create();
    $new = MinecraftAccount::factory()->active()->for($user)->create();

    SetPrimaryMinecraftAccount::run($new);

    expect($old->fresh()->is_primary)->toBeFalse()
        ->and($new->fresh()->is_primary)->toBeTrue();
});

test('does not set a removed account as primary', function () {
    $user = User::factory()->create();
    $account = MinecraftAccount::factory()->removed()->for($user)->create();

    $result = SetPrimaryMinecraftAccount::run($account);

    expect($result)->toBeFalse()
        ->and($account->fresh()->is_primary)->toBeFalse();
});

test('does not set a verifying account as primary', function () {
    $user = User::factory()->create();
    $account = MinecraftAccount::factory()->verifying()->for($user)->create();

    $result = SetPrimaryMinecraftAccount::run($account);

    expect($result)->toBeFalse()
        ->and($account->fresh()->is_primary)->toBeFalse();
});

test('does not affect other users accounts', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $account1 = MinecraftAccount::factory()->active()->primary()->for($user1)->create();
    $account2 = MinecraftAccount::factory()->active()->for($user2)->create();

    SetPrimaryMinecraftAccount::run($account2);

    expect($account1->fresh()->is_primary)->toBeTrue()
        ->and($account2->fresh()->is_primary)->toBeTrue();
});
