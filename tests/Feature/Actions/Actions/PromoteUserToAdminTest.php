<?php

declare(strict_types=1);

use App\Actions\PromoteUserToAdmin;
use App\Actions\RecordActivity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns true when user is already an admin', function () {
    // Create a user and get the existing admin role
    $user = User::factory()->create();
    $adminRole = Role::where('name', 'Admin')->first();

    // Attach admin role to user
    $user->roles()->attach($adminRole->id);

    // Mock RecordActivity to ensure it's not called
    $recordActivityMock = $this->mock(RecordActivity::class);
    $recordActivityMock->shouldNotReceive('handle');

    // Run the action
    $result = PromoteUserToAdmin::run($user);

    expect($result)->toBeTrue();
    expect($user->roles()->where('name', 'Admin')->exists())->toBeTrue();
});

it('promotes user to admin when user is not admin and role exists', function () {
    // Create a user without admin role (Admin role already exists from migrations)
    $user = User::factory()->create();

    // Verify user doesn't have admin role initially
    expect($user->roles()->where('name', 'Admin')->exists())->toBeFalse();

    // Mock RecordActivity to verify it's called
    $recordActivityMock = $this->mock(RecordActivity::class);
    $recordActivityMock->shouldReceive('handle')
        ->once()
        ->with($user, 'user_promoted_to_admin', 'Promoted to Admin role.');

    // Run the action
    $result = PromoteUserToAdmin::run($user);

    expect($result)->toBeTrue();
    expect($user->fresh()->roles()->where('name', 'Admin')->exists())->toBeTrue();
    expect($user->fresh()->promoted_at)->not->toBeNull();
});

it('returns false when admin role does not exist', function () {
    // Create a user and delete the admin role to test the failure case
    $user = User::factory()->create();

    // Delete the Admin role to test the failure scenario
    Role::where('name', 'Admin')->delete();

    // Ensure no Admin role exists
    expect(Role::where('name', 'Admin')->exists())->toBeFalse();

    // Mock RecordActivity to ensure it's not called
    $recordActivityMock = $this->mock(RecordActivity::class);
    $recordActivityMock->shouldNotReceive('handle');

    // Run the action
    $result = PromoteUserToAdmin::run($user);

    expect($result)->toBeFalse();
    expect($user->roles()->where('name', 'Admin')->exists())->toBeFalse();
});

it('does not attach duplicate admin role when user is already admin', function () {
    // Create a user and get the existing admin role
    $user = User::factory()->create();
    $adminRole = Role::where('name', 'Admin')->first();

    // Attach admin role to user
    $user->roles()->attach($adminRole->id);

    // Record initial count
    $initialRoleCount = $user->roles()->count();

    // Mock RecordActivity to ensure it's not called
    $recordActivityMock = $this->mock(RecordActivity::class);
    $recordActivityMock->shouldNotReceive('handle');

    // Run the action
    $result = PromoteUserToAdmin::run($user);

    expect($result)->toBeTrue();
    expect($user->fresh()->roles()->count())->toBe($initialRoleCount);
    expect($user->roles()->where('name', 'Admin')->count())->toBe(1);
});

it('successfully promotes user when user has other roles but not admin', function () {
    // Create a user and get existing roles
    $user = User::factory()->create();
    $moderatorRole = Role::create([
        'name' => 'Moderator',
        'description' => 'Moderator role',
    ]);
    $adminRole = Role::where('name', 'Admin')->first();

    // Give user moderator role but not admin
    $user->roles()->attach($moderatorRole->id);

    // Verify initial state
    expect($user->roles()->where('name', 'Moderator')->exists())->toBeTrue();
    expect($user->roles()->where('name', 'Admin')->exists())->toBeFalse();

    // Mock RecordActivity to verify it's called
    $recordActivityMock = $this->mock(RecordActivity::class);
    $recordActivityMock->shouldReceive('handle')
        ->once()
        ->with($user, 'user_promoted_to_admin', 'Promoted to Admin role.');

    // Run the action
    $result = PromoteUserToAdmin::run($user);

    expect($result)->toBeTrue();
    expect($user->fresh()->roles()->where('name', 'Admin')->exists())->toBeTrue();
    expect($user->roles()->where('name', 'Moderator')->exists())->toBeTrue();
    expect($user->roles()->count())->toBe(2);
});
