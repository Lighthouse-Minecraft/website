<?php

declare(strict_types=1);

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\BoardMember;
use App\Models\User;

uses()->group('board-members', 'policies');

it('allows admin to view any board members', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->can('viewAny', BoardMember::class))->toBeTrue();
});

it('allows command officer to view any board members', function () {
    $officer = User::factory()->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)->create();

    expect($officer->can('viewAny', BoardMember::class))->toBeTrue();
});

it('denies regular user from viewing board members', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', BoardMember::class))->toBeFalse();
});

it('denies crew member from managing board members', function () {
    $crewMember = User::factory()->withStaffPosition(StaffDepartment::Engineer, StaffRank::CrewMember)->create();

    expect($crewMember->can('viewAny', BoardMember::class))->toBeFalse();
});

it('allows admin to create board members', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->can('create', BoardMember::class))->toBeTrue();
});

it('allows admin to update board members', function () {
    $admin = User::factory()->admin()->create();
    $boardMember = BoardMember::factory()->create();

    expect($admin->can('update', $boardMember))->toBeTrue();
});

it('allows admin to delete board members', function () {
    $admin = User::factory()->admin()->create();
    $boardMember = BoardMember::factory()->create();

    expect($admin->can('delete', $boardMember))->toBeTrue();
});

it('denies non-command officer from managing board members', function () {
    $officer = User::factory()->withStaffPosition(StaffDepartment::Engineer, StaffRank::Officer)->create();

    expect($officer->can('viewAny', BoardMember::class))->toBeFalse();
});
