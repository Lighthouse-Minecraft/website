<?php

use App\Models\User;

use function Pest\Laravel\artisan;

describe('PromoteUserToAdmin Command', function () {
    it('promotes a user to admin by email', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        artisan('app:promote-user-to-admin', ['email' => 'test@example.com'])
            ->expectsOutput("User {$user->email} has been promoted to Admin.")
            ->assertExitCode(0);

        expect($user->fresh()->isAdmin())->toBeTrue()
            ->and($user->fresh()->admin_granted_at)->not->toBeNull();
    });

    it('shows error when user not found', function () {
        artisan('app:promote-user-to-admin', ['email' => 'nonexistent@example.com'])
            ->expectsOutput('User with email nonexistent@example.com not found.')
            ->assertExitCode(1);
    });

    it('shows info when user is already admin', function () {
        $user = User::factory()->admin()->create([
            'email' => 'admin@example.com',
        ]);

        artisan('app:promote-user-to-admin', ['email' => 'admin@example.com'])
            ->expectsOutput("User {$user->email} is already an Admin.")
            ->assertExitCode(0);
    });
});
