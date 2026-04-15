<?php

declare(strict_types=1);

use App\Enums\StaffRank;
use App\Models\User;

uses()->group('vault', 'gates');

// === manage-vault ===

it('grants manage-vault to user with Vault Manager role', function () {
    $user = User::factory()->withRole('Vault Manager')->create();

    expect($user->can('manage-vault'))->toBeTrue();
});

it('denies manage-vault to a JrCrew staff member without the Vault Manager role', function () {
    $user = User::factory()->create(['staff_rank' => StaffRank::JrCrew]);

    expect($user->can('manage-vault'))->toBeFalse();
});

it('denies manage-vault to a regular member', function () {
    $user = User::factory()->create();

    expect($user->can('manage-vault'))->toBeFalse();
});

it('grants manage-vault to an admin regardless of role', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('manage-vault'))->toBeTrue();
});

// === view-vault ===

dataset('view_vault_allowed_ranks', [
    StaffRank::JrCrew,
    StaffRank::CrewMember,
    StaffRank::Officer,
]);

it('grants view-vault to staff at or above JrCrew', function (StaffRank $rank) {
    $user = User::factory()->create(['staff_rank' => $rank]);

    expect($user->can('view-vault'))->toBeTrue();
})->with('view_vault_allowed_ranks');

it('denies view-vault to a user with no staff rank', function () {
    $user = User::factory()->create(['staff_rank' => StaffRank::None]);

    expect($user->can('view-vault'))->toBeFalse();
});

it('grants view-vault to an admin regardless of staff rank', function () {
    $user = User::factory()->admin()->create(['staff_rank' => StaffRank::None]);

    expect($user->can('view-vault'))->toBeTrue();
});
