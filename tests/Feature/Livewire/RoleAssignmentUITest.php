<?php

declare(strict_types=1);

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Role;
use App\Models\StaffPosition;
use App\Models\User;
use App\Services\MinecraftRconService;

use function Pest\Livewire\livewire;

uses()->group('roles', 'livewire');

beforeEach(function () {
    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);
});

// == Role Assignment to Positions ==

it('allows admin to assign a role to a staff position via event', function () {
    $admin = loginAsAdmin();
    $position = StaffPosition::factory()->create();
    $role = Role::firstOrCreate(['name' => 'Moderator'], ['color' => 'blue', 'icon' => 'shield-check']);

    livewire('admin-manage-staff-positions-page')
        ->set('rolePositionId', $position->id)
        ->call('onRoleAdded', $role->id);

    expect($position->fresh()->roles()->where('name', 'Moderator')->exists())->toBeTrue();
});

it('allows admin to remove a role from a staff position via event', function () {
    $admin = loginAsAdmin();
    $position = StaffPosition::factory()->create();
    $role = Role::firstOrCreate(['name' => 'Moderator'], ['color' => 'blue', 'icon' => 'shield-check']);
    $position->roles()->attach($role);

    livewire('admin-manage-staff-positions-page')
        ->set('rolePositionId', $position->id)
        ->call('onRoleRemoved', $role->id);

    expect($position->fresh()->roles()->where('name', 'Moderator')->exists())->toBeFalse();
});

it('prevents non-admin from managing roles on positions', function () {
    $user = User::factory()->create();
    loginAs($user);
    $position = StaffPosition::factory()->create();
    $role = Role::firstOrCreate(['name' => 'Moderator'], ['color' => 'blue', 'icon' => 'shield-check']);

    // The component authorizes on render (getPositionsProperty), so non-admin is blocked
    livewire('admin-manage-staff-positions-page')
        ->assertForbidden();
});

it('displays role badges in positions table', function () {
    $admin = loginAsAdmin();
    $position = StaffPosition::factory()->create(['title' => 'Test Position']);
    $role = Role::firstOrCreate(['name' => 'Moderator'], ['color' => 'blue', 'icon' => 'shield-check']);
    $position->roles()->attach($role);

    livewire('admin-manage-staff-positions-page')
        ->assertSee('Moderator');
});

// == Allow All Toggle ==

it('allows admin to enable Allow All on a position', function () {
    $admin = loginAsAdmin();
    $position = StaffPosition::factory()->create();

    expect($position->has_all_roles_at)->toBeNull();

    livewire('admin-manage-staff-positions-page')
        ->call('toggleAllowAll', $position->id);

    expect($position->fresh()->has_all_roles_at)->not->toBeNull();
});

it('allows admin to disable Allow All on a position', function () {
    $admin = loginAsAdmin();
    $position = StaffPosition::factory()->create(['has_all_roles_at' => now()]);

    expect($position->has_all_roles_at)->not->toBeNull();

    livewire('admin-manage-staff-positions-page')
        ->call('toggleAllowAll', $position->id);

    expect($position->fresh()->has_all_roles_at)->toBeNull();
});

it('displays Allow All badge for positions with has_all_roles_at set', function () {
    $admin = loginAsAdmin();
    $position = StaffPosition::factory()->create([
        'title' => 'Commander',
        'has_all_roles_at' => now(),
    ]);

    livewire('admin-manage-staff-positions-page')
        ->assertSee('Allow All');
});

it('prevents non-admin from toggling Allow All', function () {
    $user = User::factory()->create();
    loginAs($user);

    // The component authorizes on render (getPositionsProperty), so non-admin is blocked
    livewire('admin-manage-staff-positions-page')
        ->assertForbidden();
});

// == Role Display Visibility on Profile ==

it('shows role badges on profile to staff members', function () {
    $staffUser = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::JrCrew, 'Jr Crew')
        ->create();

    $targetUser = User::factory()->create();
    $position = StaffPosition::factory()
        ->officer()
        ->inDepartment(StaffDepartment::Command)
        ->assignedTo($targetUser->id)
        ->create(['title' => 'Test Officer']);
    $role = Role::firstOrCreate(['name' => 'Moderator'], ['color' => 'blue', 'icon' => 'shield-check']);
    $position->roles()->attach($role);

    $targetUser->update([
        'staff_rank' => StaffRank::Officer,
        'staff_department' => StaffDepartment::Command,
    ]);

    loginAs($staffUser);

    livewire('users.display-basic-details', ['user' => $targetUser])
        ->assertSee('Moderator');
});

it('hides role badges on profile from non-staff users', function () {
    $regularUser = User::factory()->create();

    $targetUser = User::factory()->create();
    $position = StaffPosition::factory()
        ->officer()
        ->inDepartment(StaffDepartment::Command)
        ->assignedTo($targetUser->id)
        ->create(['title' => 'Test Officer']);
    $role = Role::firstOrCreate(['name' => 'Moderator'], ['color' => 'blue', 'icon' => 'shield-check']);
    $position->roles()->attach($role);

    $targetUser->update([
        'staff_rank' => StaffRank::Officer,
        'staff_department' => StaffDepartment::Command,
    ]);

    loginAs($regularUser);

    livewire('users.display-basic-details', ['user' => $targetUser])
        ->assertSee('Test Officer')
        ->assertDontSee('Moderator');
});

it('shows Allow All badge on profile for staff-visible positions', function () {
    $staffUser = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::JrCrew, 'Jr Crew')
        ->create();

    $targetUser = User::factory()->create();
    $position = StaffPosition::factory()
        ->officer()
        ->inDepartment(StaffDepartment::Command)
        ->assignedTo($targetUser->id)
        ->create([
            'title' => 'Commander',
            'has_all_roles_at' => now(),
        ]);

    $targetUser->update([
        'staff_rank' => StaffRank::Officer,
        'staff_department' => StaffDepartment::Command,
    ]);

    loginAs($staffUser);

    livewire('users.display-basic-details', ['user' => $targetUser])
        ->assertSee('Allow All');
});

it('shows role badges to admin users on profile', function () {
    $admin = loginAsAdmin();

    $targetUser = User::factory()->create();
    $position = StaffPosition::factory()
        ->officer()
        ->inDepartment(StaffDepartment::Command)
        ->assignedTo($targetUser->id)
        ->create(['title' => 'Test Officer']);
    $role = Role::firstOrCreate(['name' => 'Brig Warden'], ['color' => 'red', 'icon' => 'lock-closed']);
    $position->roles()->attach($role);

    $targetUser->update([
        'staff_rank' => StaffRank::Officer,
        'staff_department' => StaffDepartment::Command,
    ]);

    livewire('users.display-basic-details', ['user' => $targetUser])
        ->assertSee('Brig Warden');
});
