<?php

declare(strict_types=1);

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\StaffPosition;
use App\Models\User;

uses()->group('staff', 'policies');

it('allows admin to view any staff positions', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->can('viewAny', StaffPosition::class))->toBeTrue();
});

it('allows command officer to view any staff positions', function () {
    $officer = User::factory()->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)->create();

    expect($officer->can('viewAny', StaffPosition::class))->toBeTrue();
});

it('denies regular user from viewing staff positions', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', StaffPosition::class))->toBeFalse();
});

it('denies crew member from managing staff positions', function () {
    $crewMember = User::factory()->withStaffPosition(StaffDepartment::Engineer, StaffRank::CrewMember)->create();

    expect($crewMember->can('viewAny', StaffPosition::class))->toBeFalse();
});

it('allows admin to create staff positions', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->can('create', StaffPosition::class))->toBeTrue();
});

it('allows admin to update staff positions', function () {
    $admin = User::factory()->admin()->create();
    $position = StaffPosition::factory()->create();

    expect($admin->can('update', $position))->toBeTrue();
});

it('allows admin to assign staff positions', function () {
    $admin = User::factory()->admin()->create();
    $position = StaffPosition::factory()->create();

    expect($admin->can('assign', $position))->toBeTrue();
});

it('denies non-command officer from managing staff positions', function () {
    $officer = User::factory()->withStaffPosition(StaffDepartment::Engineer, StaffRank::Officer)->create();

    expect($officer->can('viewAny', StaffPosition::class))->toBeFalse();
});
