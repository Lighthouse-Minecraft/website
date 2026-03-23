<?php

declare(strict_types=1);

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Role;
use App\Models\User;
use App\Services\MinecraftRconService;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

uses()->group('roles', 'livewire', 'rank-roles');

beforeEach(function () {
    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);
});

// == Rank Role Assignment ==

it('allows admin to assign a role to a rank', function () {
    loginAsAdmin();
    $role = Role::firstOrCreate(['name' => 'Ticket - User'], ['color' => 'blue', 'icon' => 'ticket']);

    livewire('admin-manage-staff-positions-page')
        ->set('activeRankValue', StaffRank::CrewMember->value)
        ->call('onRoleAdded', $role->id);

    expect(
        DB::table('role_staff_rank')
            ->where('role_id', $role->id)
            ->where('staff_rank', StaffRank::CrewMember->value)
            ->exists()
    )->toBeTrue();
});

it('allows admin to remove a role from a rank', function () {
    loginAsAdmin();
    $role = Role::firstOrCreate(['name' => 'Ticket - User'], ['color' => 'blue', 'icon' => 'ticket']);

    DB::table('role_staff_rank')->insert([
        'role_id' => $role->id,
        'staff_rank' => StaffRank::CrewMember->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    livewire('admin-manage-staff-positions-page')
        ->set('activeRankValue', StaffRank::CrewMember->value)
        ->call('onRoleRemoved', $role->id);

    expect(
        DB::table('role_staff_rank')
            ->where('role_id', $role->id)
            ->where('staff_rank', StaffRank::CrewMember->value)
            ->exists()
    )->toBeFalse();
});

it('prevents non-admin from managing rank roles', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)
        ->withRole('Site Config - Manager')
        ->create();
    loginAs($user);

    livewire('admin-manage-staff-positions-page')
        ->call('openRankRolesModal', StaffRank::CrewMember->value)
        ->assertForbidden();
});

it('displays rank cards on staff positions page', function () {
    loginAsAdmin();

    livewire('admin-manage-staff-positions-page')
        ->assertSee('Junior Crew Member')
        ->assertSee('Crew Member')
        ->assertSee('Officer');
});

it('displays assigned roles on rank cards', function () {
    loginAsAdmin();
    $role = Role::firstOrCreate(['name' => 'Staff Access'], ['color' => 'sky', 'icon' => 'identification']);

    DB::table('role_staff_rank')->insert([
        'role_id' => $role->id,
        'staff_rank' => StaffRank::JrCrew->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    livewire('admin-manage-staff-positions-page')
        ->assertSee('Staff Access');
});

it('non-admin staff can view rank cards in read-only mode', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)
        ->withRole('Site Config - Manager')
        ->create();
    loginAs($user);

    livewire('admin-manage-staff-positions-page')
        ->assertSee('Junior Crew Member')
        ->assertSee('Crew Member')
        ->assertSee('Officer')
        ->assertDontSee('wire:click="openRankRolesModal');
});
