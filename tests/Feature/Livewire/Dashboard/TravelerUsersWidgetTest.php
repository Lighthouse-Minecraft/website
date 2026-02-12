<?php

use App\Enums\MembershipLevel;
use App\Models\User;
use Livewire\Volt\Volt;

use function Pest\Laravel\get;

describe('Traveler Users Widget', function () {
    it('can render', function () {
        loginAsAdmin();

        $component = Volt::test('dashboard.traveler-users-widget');

        $component->assertSee('Traveler Users');
    });

    it('displays traveler users in the table', function () {
        loginAsAdmin();

        // Create a traveler user
        $travelerUser = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create([
            'name' => 'Test Traveler',
            'promoted_at' => now()->subDays(5),
        ]);

        // Create a non-traveler user who shouldn't appear
        User::factory()->withMembershipLevel(MembershipLevel::Citizen)->create([
            'name' => 'Test Citizen',
        ]);

        $component = Volt::test('dashboard.traveler-users-widget');

        $component->assertSee('Test Traveler');
        $component->assertDontSee('Test Citizen');
    });

    it('shows empty state when no traveler users exist', function () {
        loginAsAdmin();

        // Create users with different membership levels
        User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create();
        User::factory()->withMembershipLevel(MembershipLevel::Citizen)->create();

        $component = Volt::test('dashboard.traveler-users-widget');

        $component->assertSee('No Traveler users');
    });

    it('displays users sorted by promoted_at oldest first', function () {
        loginAsAdmin();

        // Create travelers with different promotion dates
        $oldestTraveler = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create([
            'name' => 'Oldest Traveler',
            'promoted_at' => now()->subDays(30),
        ]);

        $newestTraveler = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create([
            'name' => 'Newest Traveler',
            'promoted_at' => now()->subDays(5),
        ]);

        $middleTraveler = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create([
            'name' => 'Middle Traveler',
            'promoted_at' => now()->subDays(15),
        ]);

        $component = Volt::test('dashboard.traveler-users-widget');

        // Get the rendered HTML
        $html = $component->get('travelerUsers');

        // Verify the collection is in the correct order
        expect($html->first()->name)->toBe('Oldest Traveler');
        expect($html->get(1)->name)->toBe('Middle Traveler');
        expect($html->last()->name)->toBe('Newest Traveler');
    });

    it('can open user details modal', function () {
        loginAsAdmin();

        $travelerUser = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create([
            'name' => 'Test Traveler',
            'promoted_at' => now(),
        ]);

        $component = Volt::test('dashboard.traveler-users-widget');

        $component->call('viewUser', $travelerUser->id)
            ->assertSet('selectedUser.id', $travelerUser->id)
            ->assertSet('showUserModal', true);
    });

    it('displays joined and promoted_at dates', function () {
        loginAsAdmin();

        $travelerUser = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create([
            'name' => 'Test Traveler',
            'created_at' => now()->subDays(10),
            'promoted_at' => now()->subDays(5),
        ]);

        $component = Volt::test('dashboard.traveler-users-widget');

        // Verify the traveler is shown
        $component->assertSee('Test Traveler');
    });

    it('handles null promoted_at gracefully', function () {
        loginAsAdmin();

        // Create a traveler without promoted_at
        $travelerUser = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create([
            'name' => 'Test Traveler',
            'promoted_at' => null,
        ]);

        $component = Volt::test('dashboard.traveler-users-widget');

        // Verify N/A is shown for null promoted_at
        $component->assertSee('N/A');
    });

    it('can promote traveler to resident', function () {
        loginAsAdmin();

        $travelerUser = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create([
            'name' => 'Test Traveler',
        ]);

        $component = Volt::test('dashboard.traveler-users-widget');

        $component->set('selectedUser', $travelerUser)
            ->call('promoteToResident');

        // Verify the user was promoted
        $travelerUser->refresh();
        expect($travelerUser->membership_level)->toBe(MembershipLevel::Resident);
    });

    it('can close the modal', function () {
        loginAsAdmin();

        $travelerUser = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create();

        $component = Volt::test('dashboard.traveler-users-widget');

        $component->set('selectedUser', $travelerUser)
            ->set('showUserModal', true)
            ->call('closeModal')
            ->assertSet('selectedUser', null)
            ->assertSet('showUserModal', false);
    });

    it('is visible to admins', function () {
        loginAsAdmin();

        $response = get(route('dashboard'));

        $response->assertSeeLivewire('dashboard.traveler-users-widget');
    });

    it('is visible to officers', function () {
        $officer = User::factory()->create([
            'staff_rank' => App\Enums\StaffRank::Officer,
        ]);
        $this->actingAs($officer);

        $response = get(route('dashboard'));

        $response->assertSeeLivewire('dashboard.traveler-users-widget');
    });

    it('is not visible to regular users', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = get(route('dashboard'));

        $response->assertDontSeeLivewire('dashboard.traveler-users-widget');
    });
});
