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

// === view-vault ===

it('grants view-vault to a JrCrew staff member', function () {
    $user = User::factory()->create(['staff_rank' => StaffRank::JrCrew]);

    expect($user->can('view-vault'))->toBeTrue();
});

it('grants view-vault to a CrewMember', function () {
    $user = User::factory()->create(['staff_rank' => StaffRank::CrewMember]);

    expect($user->can('view-vault'))->toBeTrue();
});

it('grants view-vault to an Officer', function () {
    $user = User::factory()->create(['staff_rank' => StaffRank::Officer]);

    expect($user->can('view-vault'))->toBeTrue();
});

it('denies view-vault to a regular member with no staff rank', function () {
    $user = User::factory()->create(['staff_rank' => StaffRank::None]);

    expect($user->can('view-vault'))->toBeFalse();
});

it('denies view-vault to a user freshly registered with no staff rank (None)', function () {
    $user = User::factory()->create(['staff_rank' => StaffRank::None]);

    expect($user->can('view-vault'))->toBeFalse();
});
