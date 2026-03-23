<?php

declare(strict_types=1);

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Role;
use App\Models\StaffPosition;
use App\Models\User;
use App\Services\MinecraftRconService;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

uses()->group('profile', 'rank-roles', 'livewire');

beforeEach(function () {
    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);
});

it('shows rank roles section on profile for staff viewer', function () {
    $viewer = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::JrCrew, 'Jr Crew')
        ->withRole('Staff Access')
        ->create();

    $targetUser = User::factory()->create();
    StaffPosition::factory()
        ->officer()
        ->inDepartment(StaffDepartment::Command)
        ->assignedTo($targetUser->id)
        ->create(['title' => 'Test Officer']);
    $targetUser->update([
        'staff_rank' => StaffRank::Officer,
        'staff_department' => StaffDepartment::Command,
    ]);

    $role = Role::firstOrCreate(['name' => 'Ticket - User'], ['color' => 'blue', 'icon' => 'ticket']);
    DB::table('role_staff_rank')->insert([
        'role_id' => $role->id,
        'staff_rank' => StaffRank::Officer->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    loginAs($viewer);

    livewire('users.display-basic-details', ['user' => $targetUser])
        ->assertSee('Officer Roles')
        ->assertSee('Ticket - User');
});

it('hides rank roles section when rank has no assigned roles', function () {
    $viewer = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::JrCrew, 'Jr Crew')
        ->withRole('Staff Access')
        ->create();

    $targetUser = User::factory()->create();
    StaffPosition::factory()
        ->officer()
        ->inDepartment(StaffDepartment::Command)
        ->assignedTo($targetUser->id)
        ->create(['title' => 'Test Officer']);
    $targetUser->update([
        'staff_rank' => StaffRank::Officer,
        'staff_department' => StaffDepartment::Command,
    ]);

    loginAs($viewer);

    livewire('users.display-basic-details', ['user' => $targetUser])
        ->assertDontSee('Officer Roles');
});

it('hides rank roles from non-staff viewers', function () {
    $regularUser = User::factory()->create();

    $targetUser = User::factory()->create();
    StaffPosition::factory()
        ->officer()
        ->inDepartment(StaffDepartment::Command)
        ->assignedTo($targetUser->id)
        ->create(['title' => 'Test Officer']);
    $targetUser->update([
        'staff_rank' => StaffRank::Officer,
        'staff_department' => StaffDepartment::Command,
    ]);

    $role = Role::firstOrCreate(['name' => 'Staff Access'], ['color' => 'sky', 'icon' => 'identification']);
    DB::table('role_staff_rank')->insert([
        'role_id' => $role->id,
        'staff_rank' => StaffRank::Officer->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    loginAs($regularUser);

    livewire('users.display-basic-details', ['user' => $targetUser])
        ->assertDontSee('Officer Roles');
});

it('shows rank roles to admin viewer', function () {
    $admin = loginAsAdmin();

    $targetUser = User::factory()->create();
    StaffPosition::factory()
        ->officer()
        ->inDepartment(StaffDepartment::Command)
        ->assignedTo($targetUser->id)
        ->create(['title' => 'Test Officer']);
    $targetUser->update([
        'staff_rank' => StaffRank::Officer,
        'staff_department' => StaffDepartment::Command,
    ]);

    $role = Role::firstOrCreate(['name' => 'Meeting - Manager'], ['color' => 'violet', 'icon' => 'calendar']);
    DB::table('role_staff_rank')->insert([
        'role_id' => $role->id,
        'staff_rank' => StaffRank::Officer->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    livewire('users.display-basic-details', ['user' => $targetUser])
        ->assertSee('Officer Roles')
        ->assertSee('Meeting - Manager');
});

it('labels rank roles section with correct rank name', function () {
    $viewer = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer, 'Officer')
        ->withRole('Staff Access')
        ->create();

    $targetUser = User::factory()->create();
    StaffPosition::factory()
        ->inDepartment(StaffDepartment::Chaplain)
        ->assignedTo($targetUser->id)
        ->create(['title' => 'Test Crew']);
    $targetUser->update([
        'staff_rank' => StaffRank::CrewMember,
        'staff_department' => StaffDepartment::Chaplain,
    ]);

    $role = Role::firstOrCreate(['name' => 'Task - Department'], ['color' => 'green', 'icon' => 'clipboard-document-list']);
    DB::table('role_staff_rank')->insert([
        'role_id' => $role->id,
        'staff_rank' => StaffRank::CrewMember->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    loginAs($viewer);

    livewire('users.display-basic-details', ['user' => $targetUser])
        ->assertSee('Crew Member Roles')
        ->assertSee('Task - Department');
});
