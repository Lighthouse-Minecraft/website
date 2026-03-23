<?php

declare(strict_types=1);

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Role;
use App\Models\StaffPosition;
use App\Models\User;
use Illuminate\Support\Facades\DB;

uses()->group('roles', 'rank-roles');

// === Rank role grants access ===

it('grants hasRole when the user rank has the role via role_staff_rank', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->create();

    $role = Role::firstOrCreate(['name' => 'Ticket - User']);
    DB::table('role_staff_rank')->insert([
        'role_id' => $role->id,
        'staff_rank' => StaffRank::CrewMember->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($user->hasRole('Ticket - User'))->toBeTrue();
});

// === Rank role denies when not assigned ===

it('denies hasRole when the user rank does NOT have the role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->create();

    Role::firstOrCreate(['name' => 'Ticket - User']);

    expect($user->hasRole('Ticket - User'))->toBeFalse();
});

// === No rank inheritance ===

it('does not inherit rank roles from lower ranks', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)
        ->create();

    $role = Role::firstOrCreate(['name' => 'Task - Department']);
    DB::table('role_staff_rank')->insert([
        'role_id' => $role->id,
        'staff_rank' => StaffRank::CrewMember->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($user->hasRole('Task - Department'))->toBeFalse();
});

it('does not inherit rank roles from higher ranks', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::JrCrew)
        ->create();

    $role = Role::firstOrCreate(['name' => 'Officer Docs - Viewer']);
    DB::table('role_staff_rank')->insert([
        'role_id' => $role->id,
        'staff_rank' => StaffRank::Officer->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($user->hasRole('Officer Docs - Viewer'))->toBeFalse();
});

// === Admin bypass still works ===

it('grants hasRole to admin regardless of rank roles', function () {
    $user = User::factory()->admin()->create();

    expect($user->hasRole('Ticket - User'))->toBeTrue()
        ->and($user->hasRole('Staff Access'))->toBeTrue()
        ->and($user->hasRole('Officer Docs - Viewer'))->toBeTrue();
});

// === Position Allow All still works ===

it('grants hasRole to user with allow-all position regardless of rank roles', function () {
    $user = User::factory()->create();
    StaffPosition::factory()->assignedTo($user->id)->create(['has_all_roles_at' => now()]);
    $user = $user->fresh();

    expect($user->hasRole('Ticket - User'))->toBeTrue()
        ->and($user->hasRole('Staff Access'))->toBeTrue();
});

// === Position role still works alongside rank role ===

it('grants hasRole via position role when rank role is not assigned', function () {
    $user = User::factory()->withRole('Membership Level - Manager')->create();

    expect($user->hasRole('Membership Level - Manager'))->toBeTrue();
});

// === User with no position but has rank can get rank roles ===

it('grants hasRole via rank even without a staff position', function () {
    $user = User::factory()->create(['staff_rank' => StaffRank::CrewMember]);

    $role = Role::firstOrCreate(['name' => 'Staff Access']);
    DB::table('role_staff_rank')->insert([
        'role_id' => $role->id,
        'staff_rank' => StaffRank::CrewMember->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($user->hasRole('Staff Access'))->toBeTrue();
});

// === User with StaffRank::None gets no rank roles ===

it('denies rank roles for user with StaffRank::None', function () {
    $user = User::factory()->create(['staff_rank' => StaffRank::None]);

    $role = Role::firstOrCreate(['name' => 'Staff Access']);
    DB::table('role_staff_rank')->insert([
        'role_id' => $role->id,
        'staff_rank' => StaffRank::JrCrew->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($user->hasRole('Staff Access'))->toBeFalse();
});

// === Multiple ranks can have the same role independently ===

it('assigns same role to multiple ranks independently', function () {
    $role = Role::firstOrCreate(['name' => 'Staff Access']);

    DB::table('role_staff_rank')->insert([
        ['role_id' => $role->id, 'staff_rank' => StaffRank::JrCrew->value, 'created_at' => now(), 'updated_at' => now()],
        ['role_id' => $role->id, 'staff_rank' => StaffRank::CrewMember->value, 'created_at' => now(), 'updated_at' => now()],
        ['role_id' => $role->id, 'staff_rank' => StaffRank::Officer->value, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $jrCrew = User::factory()->create(['staff_rank' => StaffRank::JrCrew]);
    $crewMember = User::factory()->create(['staff_rank' => StaffRank::CrewMember]);
    $officer = User::factory()->create(['staff_rank' => StaffRank::Officer]);
    $none = User::factory()->create(['staff_rank' => StaffRank::None]);

    expect($jrCrew->hasRole('Staff Access'))->toBeTrue()
        ->and($crewMember->hasRole('Staff Access'))->toBeTrue()
        ->and($officer->hasRole('Staff Access'))->toBeTrue()
        ->and($none->hasRole('Staff Access'))->toBeFalse();
});
