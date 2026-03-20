<?php

declare(strict_types=1);

use App\Actions\RevokeUserAdmin;
use App\Models\Role;
use App\Models\StaffPosition;
use App\Models\User;

uses()->group('roles', 'actions');

// == Admin Flag Behavior ==

it('isAdmin returns true when admin_granted_at is set', function () {
    $user = User::factory()->admin()->create();

    expect($user->isAdmin())->toBeTrue();
});

it('isAdmin returns false when admin_granted_at is null', function () {
    $user = User::factory()->create();

    expect($user->isAdmin())->toBeFalse();
});

it('admin override grants all role checks', function () {
    $user = User::factory()->admin()->create();

    expect($user->hasRole('Moderator'))->toBeTrue()
        ->and($user->hasRole('Brig Warden'))->toBeTrue()
        ->and($user->hasRole('NonexistentRole'))->toBeTrue();
});

// == RevokeUserAdmin Action ==

it('revokes admin by setting admin_granted_at to null', function () {
    $user = User::factory()->admin()->create();

    expect($user->isAdmin())->toBeTrue();

    RevokeUserAdmin::run($user);

    expect($user->fresh()->isAdmin())->toBeFalse()
        ->and($user->fresh()->admin_granted_at)->toBeNull();
});

it('revoke is idempotent when user is not admin', function () {
    $user = User::factory()->create();

    $result = RevokeUserAdmin::run($user);

    expect($result)->toBeTrue()
        ->and($user->fresh()->isAdmin())->toBeFalse();
});

it('revoke records activity', function () {
    $user = User::factory()->admin()->create();

    RevokeUserAdmin::run($user);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'action' => 'user_admin_revoked',
    ]);
});

// == Role Resolution: No Position ==

it('user with no staff position has no roles', function () {
    $user = User::factory()->create();

    expect($user->hasRole('Moderator'))->toBeFalse()
        ->and($user->hasRole('Brig Warden'))->toBeFalse();
});

// == Role Resolution: With Position ==

it('user with staff position inherits that position roles', function () {
    $user = User::factory()->create();
    $position = StaffPosition::factory()->assignedTo($user->id)->create();
    $role = Role::firstOrCreate(['name' => 'Moderator']);
    $position->roles()->attach($role);

    expect($user->fresh()->hasRole('Moderator'))->toBeTrue()
        ->and($user->fresh()->hasRole('Brig Warden'))->toBeFalse();
});

it('user whose position loses a role immediately loses that permission', function () {
    $user = User::factory()->create();
    $position = StaffPosition::factory()->assignedTo($user->id)->create();
    $role = Role::firstOrCreate(['name' => 'Moderator']);
    $position->roles()->attach($role);

    expect($user->fresh()->hasRole('Moderator'))->toBeTrue();

    $position->roles()->detach($role);

    expect($user->fresh()->hasRole('Moderator'))->toBeFalse();
});

it('changing a user staff position changes their roles', function () {
    $user = User::factory()->create();

    $positionA = StaffPosition::factory()->assignedTo($user->id)->create();
    $roleA = Role::firstOrCreate(['name' => 'Moderator']);
    $positionA->roles()->attach($roleA);

    expect($user->fresh()->hasRole('Moderator'))->toBeTrue();

    // Vacate position A and assign position B
    $positionA->update(['user_id' => null]);
    $positionB = StaffPosition::factory()->assignedTo($user->id)->create();
    $roleB = Role::firstOrCreate(['name' => 'Brig Warden']);
    $positionB->roles()->attach($roleB);

    expect($user->fresh()->hasRole('Moderator'))->toBeFalse()
        ->and($user->fresh()->hasRole('Brig Warden'))->toBeTrue();
});

// == Role Resolution: Allow All ==

it('user with allow-all position returns true for any role', function () {
    $user = User::factory()->create();
    $position = StaffPosition::factory()->assignedTo($user->id)->create([
        'has_all_roles_at' => now(),
    ]);

    expect($user->fresh()->hasRole('Moderator'))->toBeTrue()
        ->and($user->fresh()->hasRole('Brig Warden'))->toBeTrue()
        ->and($user->fresh()->hasRole('AnythingAtAll'))->toBeTrue();
});

// == StaffPosition::hasRole ==

it('staff position hasRole returns true when position has the role', function () {
    $position = StaffPosition::factory()->create();
    $role = Role::firstOrCreate(['name' => 'Moderator']);
    $position->roles()->attach($role);

    expect($position->hasRole('Moderator'))->toBeTrue()
        ->and($position->hasRole('Brig Warden'))->toBeFalse();
});

it('staff position hasRole returns true for any role when has_all_roles_at is set', function () {
    $position = StaffPosition::factory()->create([
        'has_all_roles_at' => now(),
    ]);

    expect($position->hasRole('Moderator'))->toBeTrue()
        ->and($position->hasRole('AnythingAtAll'))->toBeTrue();
});

// == withRole Factory ==

it('withRole factory creates a position and assigns the role', function () {
    $user = User::factory()->withRole('Moderator')->create();

    expect($user->fresh()->hasRole('Moderator'))->toBeTrue()
        ->and($user->fresh()->staffPosition)->not->toBeNull()
        ->and($user->fresh()->staffPosition->roles()->where('name', 'Moderator')->exists())->toBeTrue();
});

// == Role-StaffPosition Pivot ==

it('role_staff_position pivot table works correctly', function () {
    $position = StaffPosition::factory()->create();
    $roleA = Role::firstOrCreate(['name' => 'Moderator']);
    $roleB = Role::firstOrCreate(['name' => 'Brig Warden']);

    $position->roles()->attach([$roleA->id, $roleB->id]);

    expect($position->roles()->count())->toBe(2)
        ->and($roleA->staffPositions()->count())->toBe(1);
});
