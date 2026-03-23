<?php

declare(strict_types=1);

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Meeting;
use App\Models\MeetingNote;
use App\Models\User;

uses()->group('policies', 'meetings', 'roles');

// == MeetingPolicy: viewAny == //

it('grants meeting viewAny to user with Staff Access role', function () {
    $user = User::factory()->withRole('Staff Access')->create();

    expect($user->can('viewAny', Meeting::class))->toBeTrue();
});

it('denies meeting viewAny without Staff Access role', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', Meeting::class))->toBeFalse();
});

it('grants meeting viewAny to admin', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('viewAny', Meeting::class))->toBeTrue();
});

// == MeetingPolicy: create == //

it('grants meeting create to Meeting - Manager', function () {
    $user = User::factory()->withRole('Meeting - Manager')->create();

    expect($user->can('create', Meeting::class))->toBeTrue();
});

it('denies meeting create without Meeting - Manager role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)
        ->withRole('Staff Access')
        ->create();

    expect($user->can('create', Meeting::class))->toBeFalse();
});

// == MeetingNotePolicy: create == //

it('grants meeting note create to Meeting - Manager', function () {
    $user = User::factory()->withRole('Meeting - Manager')->create();

    expect($user->can('create', MeetingNote::class))->toBeTrue();
});

it('grants meeting note create to Meeting - Secretary', function () {
    $user = User::factory()->withRole('Meeting - Secretary')->create();

    expect($user->can('create', MeetingNote::class))->toBeTrue();
});

it('grants meeting note create to Meeting - Department', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Meeting - Department')
        ->create();

    expect($user->can('create', MeetingNote::class))->toBeTrue();
});

it('denies meeting note create without meeting role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Staff Access')
        ->create();

    expect($user->can('create', MeetingNote::class))->toBeFalse();
});

// == MeetingNotePolicy: update with department scoping == //

it('grants meeting note update to Meeting - Manager for any department', function () {
    $user = User::factory()->withRole('Meeting - Manager')->create();

    $note = MeetingNote::factory()->withSectionKey('chaplain')->create();

    expect($user->can('update', $note))->toBeTrue();
});

it('grants meeting note update to Meeting - Secretary for any department', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Meeting - Secretary')
        ->create();

    $note = MeetingNote::factory()->withSectionKey('chaplain')->create();

    expect($user->can('update', $note))->toBeTrue();
});

it('grants meeting note update to Meeting - Department for own department', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Meeting - Department')
        ->create();

    $note = MeetingNote::factory()->withSectionKey('command')->create();

    expect($user->can('update', $note))->toBeTrue();
});

it('denies meeting note update to Meeting - Department for other department', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Meeting - Department')
        ->create();

    $note = MeetingNote::factory()->withSectionKey('chaplain')->create();

    expect($user->can('update', $note))->toBeFalse();
});

it('denies meeting note update without meeting role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Staff Access')
        ->create();

    $note = MeetingNote::factory()->withSectionKey('command')->create();

    expect($user->can('update', $note))->toBeFalse();
});

it('grants meeting note update to admin', function () {
    $user = User::factory()->admin()->create();

    $note = MeetingNote::factory()->create();

    expect($user->can('update', $note))->toBeTrue();
});
