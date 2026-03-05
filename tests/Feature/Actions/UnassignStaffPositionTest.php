<?php

declare(strict_types=1);

use App\Actions\UnassignStaffPosition;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\StaffPosition;
use App\Models\User;
use App\Services\MinecraftRconService;

uses()->group('staff', 'actions');

beforeEach(function () {
    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);
});

it('removes the user from the position', function () {
    $user = User::factory()->adult()->create([
        'staff_rank' => StaffRank::Officer,
        'staff_department' => StaffDepartment::Command,
        'staff_title' => 'Captain',
    ]);
    $position = StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Command)->assignedTo($user->id)->create();

    UnassignStaffPosition::run($position);

    expect($position->fresh()->user_id)->toBeNull();
});

it('clears user staff fields', function () {
    $user = User::factory()->adult()->create([
        'staff_rank' => StaffRank::Officer,
        'staff_department' => StaffDepartment::Command,
        'staff_title' => 'Captain',
    ]);
    $position = StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Command)->assignedTo($user->id)->create();

    UnassignStaffPosition::run($position);

    $user->refresh();
    expect($user->staff_rank)->toBe(StaffRank::None)
        ->and($user->staff_department)->toBeNull()
        ->and($user->staff_title)->toBeNull();
});

it('preserves bio data when unassigning', function () {
    $user = User::factory()->adult()->create([
        'staff_rank' => StaffRank::Officer,
        'staff_department' => StaffDepartment::Command,
        'staff_title' => 'Captain',
        'staff_first_name' => 'John',
        'staff_last_initial' => 'D',
        'staff_bio' => 'Test bio content',
    ]);
    $position = StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Command)->assignedTo($user->id)->create();

    UnassignStaffPosition::run($position);

    $user->refresh();
    expect($user->staff_first_name)->toBe('John')
        ->and($user->staff_last_initial)->toBe('D')
        ->and($user->staff_bio)->toBe('Test bio content');
});

it('does nothing when position is already vacant', function () {
    $position = StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Command)->create();

    UnassignStaffPosition::run($position);

    expect($position->fresh()->user_id)->toBeNull();
});

it('records activity when unassigning', function () {
    $user = User::factory()->adult()->create([
        'staff_rank' => StaffRank::Officer,
        'staff_department' => StaffDepartment::Command,
        'staff_title' => 'Captain',
    ]);
    $position = StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Command)->assignedTo($user->id)->create();

    UnassignStaffPosition::run($position);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => StaffPosition::class,
        'subject_id' => $position->id,
        'action' => 'staff_position_unassigned',
    ]);
});
