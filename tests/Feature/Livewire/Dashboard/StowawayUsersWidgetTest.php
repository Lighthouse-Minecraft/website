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

        $stowawayUser = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create([
            'name' => 'Test Stowaway',
        ]);

        $component = Volt::test('dashboard.stowaway-users-widget');

        $component->call('viewUser', $stowawayUser->id)
            ->assertSet('selectedUser.id', $stowawayUser->id)
            ->assertSet('showUserModal', true);
    });

    it('can promote stowaway to traveler', function () {
        loginAsAdmin();

        $stowawayUser = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create([
            'name' => 'Test Stowaway',
        ]);

        $component = Volt::test('dashboard.stowaway-users-widget');

        $component->set('selectedUser', $stowawayUser)
            ->call('promoteToTraveler');

        // Verify the user was promoted
        $stowawayUser->refresh();
        expect($stowawayUser->membership_level)->toBe(MembershipLevel::Traveler);
    });

    it('prevents non-admin users from promoting', function () {
        // Login as non-admin
        $user = User::factory()->create();
        test()->actingAs($user);

        $stowawayUser = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create();

        $component = Volt::test('dashboard.stowaway-users-widget');

        $component->set('selectedUser', $stowawayUser)
            ->call('promoteToTraveler');

        // Verify the user was NOT promoted
        $stowawayUser->refresh();
        expect($stowawayUser->membership_level)->toBe(MembershipLevel::Stowaway);
    });

    it('can close the modal', function () {
        loginAsAdmin();

        $stowawayUser = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create();

        $component = Volt::test('dashboard.stowaway-users-widget');

        $component->call('viewUser', $stowawayUser->id)
            ->assertSet('showUserModal', true)
            ->call('closeModal')
            ->assertSet('showUserModal', false)
            ->assertSet('selectedUser', null);
    });
});

describe('Stowaway Users Widget - Rules Agreed By', function () {
    it('shows Self when rules_accepted_by_user_id matches the user', function () {
        loginAsAdmin();

        $stowaway = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create([
            'rules_accepted_at' => now(),
        ]);
        $stowaway->update(['rules_accepted_by_user_id' => $stowaway->id]);

        Volt::test('dashboard.stowaway-users-widget')
            ->call('viewUser', $stowaway->id)
            ->assertSee('Rules Agreed By')
            ->assertSee('Self');
    });

    it('shows parent name, email and profile link when a parent agreed', function () {
        loginAsAdmin();

        $parent = User::factory()->adult()->create(['name' => 'Parent User', 'email' => 'parent@example.com']);
        $stowaway = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create([
            'rules_accepted_at' => now(),
            'rules_accepted_by_user_id' => $parent->id,
        ]);

        Volt::test('dashboard.stowaway-users-widget')
            ->call('viewUser', $stowaway->id)
            ->assertSee('Rules Agreed By')
            ->assertSee('Parent User')
            ->assertSee('parent@example.com')
            ->assertSee('(parent)');
    });

    it('shows Not yet agreed when rules_accepted_by_user_id is null', function () {
        loginAsAdmin();

        $stowaway = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create([
            'rules_accepted_at' => null,
            'rules_accepted_by_user_id' => null,
        ]);

        Volt::test('dashboard.stowaway-users-widget')
            ->call('viewUser', $stowaway->id)
            ->assertSee('Rules Agreed By')
            ->assertSee('Not yet agreed');
    });

    it('shows Not yet agreed when parent who agreed is later deleted (nullOnDelete cascade)', function () {
        loginAsAdmin();

        $parent = User::factory()->adult()->create();
        $stowaway = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create([
            'rules_accepted_at' => now(),
            'rules_accepted_by_user_id' => $parent->id,
        ]);

        // Deleting the parent nullifies rules_accepted_by_user_id via nullOnDelete cascade
        $parent->delete();
        $stowaway->refresh();

        Volt::test('dashboard.stowaway-users-widget')
            ->call('viewUser', $stowaway->id)
            ->assertSee('Rules Agreed By')
            ->assertSee('Not yet agreed');
    });
});

describe('Stowaway Users Widget - Permissions', function () {
    it('can be seen by user with Membership Level - Manager role', function () {
        $user = User::factory()
            ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::JrCrew, 'Jr Quartermaster')
            ->withRole('Staff Access')
            ->withRole('Membership Level - Manager')
            ->create();
        loginAs($user);

        get('dashboard')
            ->assertSee('Stowaway Users')
            ->assertSeeLivewire('dashboard.stowaway-users-widget');
    });

    it('cannot be viewed by non-staff', function ($user) {
        loginAs($user);

        get('dashboard')
            ->assertDontSee('Stowaway Users')
            ->assertDontSeeLivewire('dashboard.stowaway-users-widget');
    })->with('memberAll');

    it('cannot be viewed by staff without Membership Level - Manager role', function () {
        $user = User::factory()->withStaffPosition(StaffDepartment::Engineer, StaffRank::Officer, 'Engineer Officer')->withRole('Staff Access')->create();
        loginAs($user);

        get('dashboard')
            ->assertDontSee('Stowaway Users')
            ->assertDontSeeLivewire('dashboard.stowaway-users-widget');
    });

    it('allows user with Membership Level - Manager role to promote stowaway users', function () {
        $user = User::factory()
            ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::CrewMember, 'Quartermaster Crew')
            ->withRole('Staff Access')
            ->withRole('Membership Level - Manager')
            ->create();
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
    });
});
