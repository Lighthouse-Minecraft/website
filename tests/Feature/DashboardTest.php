<?php

use App\Enums\MembershipLevel;
use App\Models\User;

describe('Dashboard', function () {
    it('redirects guests to the login page', function () {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    });

    it('allows authenticated users to visit the dashboard', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
    });

    describe('Stowaway Widget', function () {
        it('shows the stowaway widget only for admin users', function () {
            $admin = loginAsAdmin();

            $response = $this->get('/dashboard');

            $response->assertSeeLivewire('dashboard.stowaway-users-widget');
        });

        it('does not show the stowaway widget for non-admin users', function () {
            $user = User::factory()->create();
            $this->actingAs($user);

            $response = $this->get('/dashboard');

            $response->assertDontSeeLivewire('dashboard.stowaway-users-widget');
        });

        it('displays stowaway users in the widget for admins', function () {
            $admin = loginAsAdmin();

            // Create some test users
            $stowawayUser = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create([
                'name' => 'John Stowaway',
            ]);
            $travelerUser = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create([
                'name' => 'Jane Traveler',
            ]);

            $response = $this->get('/dashboard');

            $response->assertSee('John Stowaway');
            $response->assertDontSee('Jane Traveler');
        });

        it('shows empty state when no stowaway users exist', function () {
            $admin = loginAsAdmin();

            // Create only non-stowaway users
            User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create();
            User::factory()->withMembershipLevel(MembershipLevel::Citizen)->create();

            $response = $this->get('/dashboard');

            $response->assertSee('No Stowaway users found.');
        });
    });

    describe('Stowaway Widget Interactions', function () {
        it('can view user details through the modal', function () {
            $admin = loginAsAdmin();

            $stowawayUser = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create([
                'name' => 'Test Stowaway',
                'email' => 'stowaway@example.com',
            ]);

            $this->get('/dashboard')
                ->assertSeeLivewire('dashboard.stowaway-users-widget')
                ->assertSee('Test Stowaway');

            // Test the Livewire component directly to verify modal functionality
            \Livewire\Volt\Volt::test('dashboard.stowaway-users-widget')
                ->call('viewUser', $stowawayUser->id)
                ->assertSet('selectedUser.id', $stowawayUser->id)
                ->assertSet('showUserModal', true)
                ->assertSee($stowawayUser->email)
                ->assertSee($stowawayUser->membership_level->label());
        });

        it('allows admins to promote stowaway users to traveler', function () {
            $admin = loginAsAdmin();

            $stowawayUser = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create([
                'name' => 'Test Stowaway',
            ]);

            // Ensure the user starts as Stowaway
            expect($stowawayUser->membership_level)->toBe(MembershipLevel::Stowaway);

            // Test promotion through the Livewire component
            \Livewire\Volt\Volt::test('dashboard.stowaway-users-widget')
                ->set('selectedUser', $stowawayUser)
                ->call('promoteToTraveler');

            // Verify the user was promoted
            $stowawayUser->refresh();
            expect($stowawayUser->membership_level)->toBe(MembershipLevel::Traveler);
        });

        it('prevents non-admin users from promoting users', function () {
            $regularUser = User::factory()->create();
            $this->actingAs($regularUser);

            $stowawayUser = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create();

            // Test that promotion fails for non-admin
            \Livewire\Volt\Volt::test('dashboard.stowaway-users-widget')
                ->set('selectedUser', $stowawayUser)
                ->call('promoteToTraveler');

            // Verify the user was NOT promoted
            $stowawayUser->refresh();
            expect($stowawayUser->membership_level)->toBe(MembershipLevel::Stowaway);
        });

        it('records activity when promoting a user', function () {
            $admin = loginAsAdmin();

            $stowawayUser = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create();

            // Check that no promotion activity exists initially
            expect(\App\Models\ActivityLog::where('subject_type', \App\Models\User::class)
                ->where('subject_id', $stowawayUser->id)
                ->where('action', 'user_promoted')
                ->count())->toBe(0);

            // Promote the user
            \Livewire\Volt\Volt::test('dashboard.stowaway-users-widget')
                ->set('selectedUser', $stowawayUser)
                ->call('promoteToTraveler');

            // Verify activity was recorded
            expect(\App\Models\ActivityLog::where('subject_type', \App\Models\User::class)
                ->where('subject_id', $stowawayUser->id)
                ->where('action', 'user_promoted')
                ->count())->toBeGreaterThan(0);
        });

        it('handles edge cases gracefully', function () {
            $admin = loginAsAdmin();

            // Test promoting without selected user
            \Livewire\Volt\Volt::test('dashboard.stowaway-users-widget')
                ->call('promoteToTraveler');

            // Test viewing non-existent user (should throw exception)
            expect(fn () => \Livewire\Volt\Volt::test('dashboard.stowaway-users-widget')
                ->call('viewUser', 99999)
            )->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        });

        it('closes modal properly', function () {
            $admin = loginAsAdmin();
            $stowawayUser = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create();

            \Livewire\Volt\Volt::test('dashboard.stowaway-users-widget')
                ->call('viewUser', $stowawayUser->id)
                ->assertSet('showUserModal', true)
                ->call('closeModal')
                ->assertSet('showUserModal', false)
                ->assertSet('selectedUser', null);
        });
    });

    describe('Integration with PromoteUser Action', function () {
        it('uses the PromoteUser action correctly', function () {
            $admin = loginAsAdmin();

            $stowawayUser = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create();

            // Promote through the action directly to ensure it works
            \App\Actions\PromoteUser::run($stowawayUser, MembershipLevel::Traveler);

            $stowawayUser->refresh();
            expect($stowawayUser->membership_level)->toBe(MembershipLevel::Traveler);
        });

        it('respects max promotion level in PromoteUser action', function () {
            $admin = loginAsAdmin();

            $stowawayUser = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create();

            // The action should promote to Traveler when called without max level
            \App\Actions\PromoteUser::run($stowawayUser);

            $stowawayUser->refresh();
            expect($stowawayUser->membership_level)->toBe(MembershipLevel::Traveler);
        });
    });
});
