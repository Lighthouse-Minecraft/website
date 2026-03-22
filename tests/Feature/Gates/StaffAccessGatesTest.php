<?php

declare(strict_types=1);

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Role;
use App\Models\StaffPosition;
use App\Models\User;
use Illuminate\Support\Facades\DB;

uses()->group('gates', 'roles', 'staff-access');

// === view-acp ===

it('grants view-acp to user with Staff Access role via position', function () {
    $user = User::factory()->withRole('Staff Access')->create();

    expect($user->can('view-acp'))->toBeTrue();
});

it('grants view-acp to user with Staff Access role via rank', function () {
    $user = User::factory()->create(['staff_rank' => StaffRank::JrCrew]);
    $role = Role::firstOrCreate(['name' => 'Staff Access']);
    DB::table('role_staff_rank')->insert([
        'role_id' => $role->id,
        'staff_rank' => StaffRank::JrCrew->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($user->can('view-acp'))->toBeTrue();
});

it('denies view-acp to staff without Staff Access role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::Officer)
        ->create();

    expect($user->can('view-acp'))->toBeFalse();
});

// === view-ready-room ===

it('grants view-ready-room to user with Staff Access role', function () {
    $user = User::factory()->withRole('Staff Access')->create();

    expect($user->can('view-ready-room'))->toBeTrue();
});

it('denies view-ready-room to staff without Staff Access role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)
        ->create();

    expect($user->can('view-ready-room'))->toBeFalse();
});

// === view-docs-staff ===

it('grants view-docs-staff to user with Staff Access role not in brig', function () {
    $user = User::factory()->withRole('Staff Access')->create(['in_brig' => false]);

    expect($user->can('view-docs-staff'))->toBeTrue();
});

it('denies view-docs-staff to user with Staff Access role in brig', function () {
    $user = User::factory()->withRole('Staff Access')->create(['in_brig' => true]);

    expect($user->can('view-docs-staff'))->toBeFalse();
});

it('denies view-docs-staff to staff without Staff Access role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::JrCrew)
        ->create();

    expect($user->can('view-docs-staff'))->toBeFalse();
});

// === view-docs-officer ===

it('grants view-docs-officer to user with Officer Docs - Viewer role not in brig', function () {
    $user = User::factory()->withRole('Officer Docs - Viewer')->create(['in_brig' => false]);

    expect($user->can('view-docs-officer'))->toBeTrue();
});

it('denies view-docs-officer to user with Officer Docs - Viewer role in brig', function () {
    $user = User::factory()->withRole('Officer Docs - Viewer')->create(['in_brig' => true]);

    expect($user->can('view-docs-officer'))->toBeFalse();
});

it('denies view-docs-officer to user with only Staff Access role', function () {
    $user = User::factory()->withRole('Staff Access')->create(['in_brig' => false]);

    expect($user->can('view-docs-officer'))->toBeFalse();
});

it('denies view-docs-officer to officer without Officer Docs - Viewer role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)
        ->create();

    expect($user->can('view-docs-officer'))->toBeFalse();
});

// === edit-staff-bio ===

it('grants edit-staff-bio to user with Staff Access role', function () {
    $user = User::factory()->withRole('Staff Access')->create();

    expect($user->can('edit-staff-bio'))->toBeTrue();
});

it('grants edit-staff-bio to board member without Staff Access role', function () {
    $user = User::factory()->create(['is_board_member' => true]);

    expect($user->can('edit-staff-bio'))->toBeTrue();
});

it('denies edit-staff-bio to staff without Staff Access role or board membership', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
        ->create();

    expect($user->can('edit-staff-bio'))->toBeFalse();
});

// === view-user-discipline-reports ===

it('grants view-user-discipline-reports to user with Staff Access role', function () {
    $user = User::factory()->withRole('Staff Access')->create();
    $target = User::factory()->create();

    expect($user->can('view-user-discipline-reports', $target))->toBeTrue();
});

it('grants view-user-discipline-reports to user viewing own reports', function () {
    $user = User::factory()->create();

    expect($user->can('view-user-discipline-reports', $user))->toBeTrue();
});

it('grants view-user-discipline-reports to parent viewing child reports', function () {
    $parent = User::factory()->create();
    $child = User::factory()->create();
    $parent->children()->attach($child->id);

    expect($parent->can('view-user-discipline-reports', $child))->toBeTrue();
});

it('denies view-user-discipline-reports to staff without Staff Access role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::JrCrew)
        ->create();
    $target = User::factory()->create();

    expect($user->can('view-user-discipline-reports', $target))->toBeFalse();
});

// === Admin bypass for all migrated gates ===

it('grants all Staff Access gates to admin', function () {
    $user = User::factory()->admin()->create();
    $target = User::factory()->create();

    expect($user->can('view-acp'))->toBeTrue()
        ->and($user->can('view-ready-room'))->toBeTrue()
        ->and($user->can('view-docs-staff'))->toBeTrue()
        ->and($user->can('view-docs-officer'))->toBeTrue()
        ->and($user->can('edit-staff-bio'))->toBeTrue()
        ->and($user->can('view-user-discipline-reports', $target))->toBeTrue();
});

// === Allow All bypass for all migrated gates ===

it('grants all Staff Access gates to user with allow-all position', function () {
    $user = User::factory()->create();
    StaffPosition::factory()->assignedTo($user->id)->create(['has_all_roles_at' => now()]);
    $user = $user->fresh();
    $target = User::factory()->create();

    expect($user->can('view-acp'))->toBeTrue()
        ->and($user->can('view-ready-room'))->toBeTrue()
        ->and($user->can('view-docs-staff'))->toBeTrue()
        ->and($user->can('view-docs-officer'))->toBeTrue()
        ->and($user->can('edit-staff-bio'))->toBeTrue()
        ->and($user->can('view-user-discipline-reports', $target))->toBeTrue();
});

// === Rank role + position role combination ===

it('grants view-acp via rank role when position has different roles', function () {
    $user = User::factory()
        ->withRole('Membership Level - Manager')
        ->create(['staff_rank' => StaffRank::JrCrew]);

    $role = Role::firstOrCreate(['name' => 'Staff Access']);
    DB::table('role_staff_rank')->insert([
        'role_id' => $role->id,
        'staff_rank' => StaffRank::JrCrew->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($user->can('view-acp'))->toBeTrue()
        ->and($user->can('manage-stowaway-users'))->toBeTrue();
});
