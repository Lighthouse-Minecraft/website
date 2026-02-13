<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

describe('PromoteUserToAdmin Command', function () {
    it('promotes a user to admin by email', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        artisan('app:promote-user-to-admin', ['email' => 'test@example.com'])
            ->expectsOutput("User {$user->email} has been promoted to Admin.")
            ->assertExitCode(0);

        expect($user->fresh()->roles()->where('name', 'Admin')->exists())->toBeTrue();
        expect($user->fresh()->promoted_at)->not->toBeNull();
    });

    it('shows error when user not found', function () {
        artisan('app:promote-user-to-admin', ['email' => 'nonexistent@example.com'])
            ->expectsOutput('User with email nonexistent@example.com not found.')
            ->assertExitCode(1);
    });

    it('shows info when user is already admin', function () {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
        ]);

        $adminRole = Role::where('name', 'Admin')->first();
        $user->roles()->attach($adminRole->id);

        artisan('app:promote-user-to-admin', ['email' => 'admin@example.com'])
            ->expectsOutput("User {$user->email} is already an Admin.")
            ->assertExitCode(0);
    });

    it('shows error when admin role does not exist', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        Role::where('name', 'Admin')->delete();

        artisan('app:promote-user-to-admin', ['email' => 'test@example.com'])
            ->expectsOutput('Admin role not found.')
            ->assertExitCode(1);
    });
});
