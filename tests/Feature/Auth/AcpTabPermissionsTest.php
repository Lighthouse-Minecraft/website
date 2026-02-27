<?php

declare(strict_types=1);

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\DiscordAccount;
use App\Models\MinecraftAccount;
use App\Models\User;

test('engineering jr crew can pass view-mc-command-log gate', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Engineer, StaffRank::JrCrew)
        ->create();

    expect($user->can('view-mc-command-log'))->toBeTrue();
});

test('engineering jr crew can pass view-activity-log gate', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Engineer, StaffRank::JrCrew)
        ->create();

    expect($user->can('view-activity-log'))->toBeTrue();
});

test('any officer can pass view-mc-command-log gate', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::Officer)
        ->create();

    expect($user->can('view-mc-command-log'))->toBeTrue();
});

test('any officer can pass view-activity-log gate', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Steward, StaffRank::Officer)
        ->create();

    expect($user->can('view-activity-log'))->toBeTrue();
});

test('non-engineering non-officer is denied mc command log gate', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
        ->create();

    expect($user->can('view-mc-command-log'))->toBeFalse();
});

test('non-engineering non-officer is denied activity log gate', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::JrCrew)
        ->create();

    expect($user->can('view-activity-log'))->toBeFalse();
});

test('any officer can viewAny minecraft accounts', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Steward, StaffRank::Officer)
        ->create();

    expect($user->can('viewAny', MinecraftAccount::class))->toBeTrue();
});

test('any officer can viewAny discord accounts', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::Officer)
        ->create();

    expect($user->can('viewAny', DiscordAccount::class))->toBeTrue();
});

test('crew member from non-engineering dept cannot viewAny minecraft accounts', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
        ->create();

    expect($user->can('viewAny', MinecraftAccount::class))->toBeFalse();
});

test('engineering staff can pass viewACP gate', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Engineer, StaffRank::JrCrew)
        ->create();

    expect($user->can('viewACP'))->toBeTrue();
});

test('regular user without staff position cannot pass viewACP gate', function () {
    $user = User::factory()->create();

    expect($user->can('viewACP'))->toBeFalse();
});
