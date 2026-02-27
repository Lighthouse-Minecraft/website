<?php

declare(strict_types=1);

use App\Actions\AutoAssignPrimaryAccount;
use App\Models\MinecraftAccount;
use App\Models\User;

test('assigns first active account as primary when user has no primary', function () {
    $user = User::factory()->create();
    $first = MinecraftAccount::factory()->active()->for($user)->create();
    $second = MinecraftAccount::factory()->active()->for($user)->create();

    AutoAssignPrimaryAccount::run($user);

    expect($first->fresh()->is_primary)->toBeTrue()
        ->and($second->fresh()->is_primary)->toBeFalse();
});

test('does nothing when user already has a primary account', function () {
    $user = User::factory()->create();
    $primary = MinecraftAccount::factory()->active()->primary()->for($user)->create();
    $other = MinecraftAccount::factory()->active()->for($user)->create();

    AutoAssignPrimaryAccount::run($user);

    expect($primary->fresh()->is_primary)->toBeTrue()
        ->and($other->fresh()->is_primary)->toBeFalse();
});

test('does nothing when user has no active accounts', function () {
    $user = User::factory()->create();
    $removed = MinecraftAccount::factory()->removed()->for($user)->create();

    AutoAssignPrimaryAccount::run($user);

    expect($removed->fresh()->is_primary)->toBeFalse();
});

test('skips removed accounts when auto-assigning', function () {
    $user = User::factory()->create();
    $removed = MinecraftAccount::factory()->removed()->for($user)->create();
    $active = MinecraftAccount::factory()->active()->for($user)->create();

    AutoAssignPrimaryAccount::run($user);

    expect($removed->fresh()->is_primary)->toBeFalse()
        ->and($active->fresh()->is_primary)->toBeTrue();
});
