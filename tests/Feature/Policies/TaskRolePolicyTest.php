<?php

declare(strict_types=1);

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Task;
use App\Models\User;

uses()->group('policies', 'tasks', 'roles');

// == create == //

it('grants task create to user with Task - Manager role', function () {
    $user = User::factory()->withRole('Task - Manager')->create();

    expect($user->can('create', Task::class))->toBeTrue();
});

it('grants task create to user with Task - Department role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Task - Department')
        ->create();

    expect($user->can('create', Task::class))->toBeTrue();
});

it('denies task create without Task role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Staff Access')
        ->create();

    expect($user->can('create', Task::class))->toBeFalse();
});

it('grants task create to admin', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('create', Task::class))->toBeTrue();
});

// == update with department scoping == //

it('grants task update to Task - Manager for any department', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)
        ->withRole('Task - Manager')
        ->create();

    $task = Task::factory()->withDepartment('chaplain')->create();

    expect($user->can('update', $task))->toBeTrue();
});

it('grants task update to Task - Department for own department', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Task - Department')
        ->create();

    $task = Task::factory()->withDepartment('command')->create();

    expect($user->can('update', $task))->toBeTrue();
});

it('denies task update to Task - Department for other department', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Task - Department')
        ->create();

    $task = Task::factory()->withDepartment('chaplain')->create();

    expect($user->can('update', $task))->toBeFalse();
});

it('denies task update without Task role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Staff Access')
        ->create();

    $task = Task::factory()->withDepartment('command')->create();

    expect($user->can('update', $task))->toBeFalse();
});

it('grants task update to admin', function () {
    $user = User::factory()->admin()->create();
    $task = Task::factory()->create();

    expect($user->can('update', $task))->toBeTrue();
});
