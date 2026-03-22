<?php

declare(strict_types=1);

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\DiscordAccount;
use App\Models\MinecraftAccount;
use App\Models\User;

test('user with Logs - Viewer role can pass view-mc-command-log gate', function () {
    $user = User::factory()
        ->withRole('Logs - Viewer')
        ->create();

    expect($user->can('view-mc-command-log'))->toBeTrue();
});

test('user with Logs - Viewer role can pass view-activity-log gate', function () {
    $user = User::factory()
        ->withRole('Logs - Viewer')
        ->create();

    expect($user->can('view-activity-log'))->toBeTrue();
});

test('user with Logs - Viewer role can pass view-discord-api-log gate', function () {
    $user = User::factory()
        ->withRole('Logs - Viewer')
        ->create();

    expect($user->can('view-discord-api-log'))->toBeTrue();
});

test('admin can pass view-mc-command-log gate', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('view-mc-command-log'))->toBeTrue();
});

test('admin can pass view-activity-log gate', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('view-activity-log'))->toBeTrue();
});

test('admin can pass view-discord-api-log gate', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('view-discord-api-log'))->toBeTrue();
});

test('user without Logs - Viewer role is denied discord api log gate', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
        ->create();

    expect($user->can('view-discord-api-log'))->toBeFalse();
});

test('user without Logs - Viewer role is denied mc command log gate', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
        ->create();

    expect($user->can('view-mc-command-log'))->toBeFalse();
});

test('user without Logs - Viewer role is denied activity log gate', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::JrCrew)
        ->create();

    expect($user->can('view-activity-log'))->toBeFalse();
});

test('user with User - Manager role can viewAny minecraft accounts', function () {
    $user = User::factory()->withRole('User - Manager')->create();

    expect($user->can('viewAny', MinecraftAccount::class))->toBeTrue();
});

test('user with User - Manager role can viewAny discord accounts', function () {
    $user = User::factory()->withRole('User - Manager')->create();

    expect($user->can('viewAny', DiscordAccount::class))->toBeTrue();
});

test('officer without User - Manager role cannot viewAny minecraft accounts', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Steward, StaffRank::Officer)
        ->create();

    expect($user->can('viewAny', MinecraftAccount::class))->toBeFalse();
});

test('engineering staff can pass view-acp gate', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Engineer, StaffRank::JrCrew)
        ->create();

    expect($user->can('view-acp'))->toBeTrue();
});

test('regular user without staff position cannot pass view-acp gate', function () {
    $user = User::factory()->create();

    expect($user->can('view-acp'))->toBeFalse();
});
