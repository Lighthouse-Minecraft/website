<?php

use App\Enums\MembershipLevel;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\User;
use Livewire\Volt\Volt;

use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

describe('Stowaway Users Widget', function () {
    it('can render', function () {
        loginAsAdmin();

        $component = Volt::test('dashboard.stowaway-users-widget');

        $component->assertSee('Stowaway Users');
    });

    it('displays stowaway users in the table', function () {
        loginAsAdmin();

        // Create a stowaway user
        $stowawayUser = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create([
            'name' => 'Test Stowaway',
        ]);

        // Create a non-stowaway user who shouldn't appear
        User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create([
            'name' => 'Test Traveler',
        ]);

        $component = Volt::test('dashboard.stowaway-users-widget');

        $component->assertSee('Test Stowaway');
        $component->assertDontSee('Test Traveler');
    });

    it('shows empty state when no stowaway users exist', function () {
        loginAsAdmin();

        // Create users with different membership levels
        User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create();
        User::factory()->withMembershipLevel(MembershipLevel::Citizen)->create();

        $component = Volt::test('dashboard.stowaway-users-widget');

        $component->assertSee('No Stowaway users found.');
    });

    it('can open user details modal', function () {
        loginAsAdmin();

        $stowawaUser = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create([
            'name' => 'Test Stowaway',
        ]);

        $component = Volt::test('dashboard.stowaway-users-widget');

        $component->call('viewUser', $stowawaUser->id)
            ->assertSet('selectedUser.id', $stowawaUser->id)
            ->assertSet('showUserModal', true);
    });

    it('can promote stowaway to traveler', function () {
        loginAsAdmin();

        $stowawaUser = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create([
            'name' => 'Test Stowaway',
        ]);

        $component = Volt::test('dashboard.stowaway-users-widget');

        $component->set('selectedUser', $stowawaUser)
            ->call('promoteToTraveler');

        // Verify the user was promoted
        $stowawaUser->refresh();
        expect($stowawaUser->membership_level)->toBe(MembershipLevel::Traveler);
    });

    it('prevents non-admin users from promoting', function () {
        // Login as non-admin
        $user = User::factory()->create();
        test()->actingAs($user);

        $stowawaUser = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create();

        $component = Volt::test('dashboard.stowaway-users-widget');

        $component->set('selectedUser', $stowawaUser)
            ->call('promoteToTraveler');

        // Verify the user was NOT promoted
        $stowawaUser->refresh();
        expect($stowawaUser->membership_level)->toBe(MembershipLevel::Stowaway);
    });

    it('can close the modal', function () {
        loginAsAdmin();

        $stowawaUser = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create();

        $component = Volt::test('dashboard.stowaway-users-widget');

        $component->call('viewUser', $stowawaUser->id)
            ->assertSet('showUserModal', true)
            ->call('closeModal')
            ->assertSet('showUserModal', false)
            ->assertSet('selectedUser', null);
    });
});

describe('Stowaway Users Widget - Permissions', function () {
    it('can be seen by officers', function ($user) {
        loginAs($user);

        get('dashboard')
            ->assertSee('Stowaway Users')
            ->assertSeeLivewire('dashboard.stowaway-users-widget');
    })->with('officers');

    it('can be seen by crew members in the quartermaster department', function () {
        $user = User::factory()->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::CrewMember, 'Quartermaster Crew')->create();

        loginAs($user);

        get('dashboard')
            ->assertSee('Stowaway Users')
            ->assertSeeLivewire('dashboard.stowaway-users-widget');
    });

    it('cannot be viewed by non-officers', function ($user) {
        loginAs($user);

        get('dashboard')
            ->assertDontSee('Stowaway Users')
            ->assertDontSeeLivewire('dashboard.stowaway-users-widget');
    })->with('memberAll');

    it('cannot be viewed by JrCrew', function ($user) {
        loginAs($user);

        get('dashboard')
            ->assertDontSee('Stowaway Users')
            ->assertDontSeeLivewire('dashboard.stowaway-users-widget');
    })->with('rankAtMostJrCrew');

    it('allows officers to promote stowaway users', function ($user) {
        loginAs($user);
        $member = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create();

        livewire('dashboard.stowaway-users-widget')
            ->set('selectedUser', $member)
            ->call('promoteToTraveler')
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $member->id,
            'membership_level' => MembershipLevel::Traveler,
        ]);
    })->with('officers');
});
