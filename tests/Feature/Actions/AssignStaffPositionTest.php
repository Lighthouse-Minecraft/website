<?php

declare(strict_types=1);

use App\Actions\AssignStaffPosition;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\StaffPosition;
use App\Models\User;
use App\Services\MinecraftRconService;

uses()->group('staff', 'actions');

beforeEach(function () {
    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);
});

it('assigns a user to a vacant position', function () {
    $user = User::factory()->adult()->create();
    $position = StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Command)->create();

    AssignStaffPosition::run($position, $user);

    expect($position->fresh()->user_id)->toBe($user->id);
});

it('syncs staff rank department and title on the user', function () {
    $user = User::factory()->adult()->create();
    $position = StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Engineer)->create([
        'title' => 'Head Engineer',
    ]);

    AssignStaffPosition::run($position, $user);

    $user->refresh();
    expect($user->staff_rank)->toBe(StaffRank::Officer)
        ->and($user->staff_department)->toBe(StaffDepartment::Engineer)
        ->and($user->staff_title)->toBe('Head Engineer');
});

it('sets JrCrew rank when user is under 17 with crew member position', function () {
    $user = User::factory()->create([
        'date_of_birth' => now()->subYears(16)->subMonth(),
    ]);
    $position = StaffPosition::factory()->crewMember()->inDepartment(StaffDepartment::Steward)->create();

    AssignStaffPosition::run($position, $user);

    $user->refresh();
    expect($user->staff_rank)->toBe(StaffRank::JrCrew);
});

it('sets CrewMember rank when user is 17 or older with crew member position', function () {
    $user = User::factory()->create([
        'date_of_birth' => now()->subYears(17)->subMonth(),
    ]);
    $position = StaffPosition::factory()->crewMember()->inDepartment(StaffDepartment::Steward)->create();

    AssignStaffPosition::run($position, $user);

    $user->refresh();
    expect($user->staff_rank)->toBe(StaffRank::CrewMember);
});

it('sets CrewMember rank when user age is unknown with crew member position', function () {
    $user = User::factory()->withoutDob()->create();
    $position = StaffPosition::factory()->crewMember()->inDepartment(StaffDepartment::Steward)->create();

    AssignStaffPosition::run($position, $user);

    $user->refresh();
    expect($user->staff_rank)->toBe(StaffRank::CrewMember);
});

it('clears old position when reassigning user to a new position', function () {
    $user = User::factory()->adult()->create();
    $oldPosition = StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Command)->assignedTo($user->id)->create();
    $newPosition = StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Engineer)->create();

    AssignStaffPosition::run($newPosition, $user);

    expect($oldPosition->fresh()->user_id)->toBeNull()
        ->and($newPosition->fresh()->user_id)->toBe($user->id);
});

it('unassigns existing holder when position is already filled', function () {
    $existingUser = User::factory()->adult()->create();
    $newUser = User::factory()->adult()->create();
    $position = StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Command)->assignedTo($existingUser->id)->create();

    // Set up existing user's staff fields
    $existingUser->update([
        'staff_rank' => StaffRank::Officer,
        'staff_department' => StaffDepartment::Command,
        'staff_title' => $position->title,
    ]);

    AssignStaffPosition::run($position, $newUser);

    expect($position->fresh()->user_id)->toBe($newUser->id)
        ->and($existingUser->fresh()->staff_rank)->toBe(StaffRank::None);
});

it('records activity when assigning', function () {
    $user = User::factory()->adult()->create();
    $position = StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Command)->create();

    AssignStaffPosition::run($position, $user);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => StaffPosition::class,
        'subject_id' => $position->id,
        'action' => 'staff_position_assigned',
    ]);
});
