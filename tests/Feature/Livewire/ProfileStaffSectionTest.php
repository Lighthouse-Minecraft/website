<?php

declare(strict_types=1);

use App\Models\User;

uses()->group('background-checks', 'profile', 'livewire');

it('shows Staff section heading to a user with Staff Access role', function () {
    $staff = User::factory()->withRole('Staff Access')->create();
    $target = User::factory()->create();
    loginAs($staff);

    $this->get(route('profile.show', $target))
        ->assertSee('Staff');
});

it('hides Staff section heading from a non-staff user', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create();
    loginAs($viewer);

    $this->get(route('profile.show', $target))
        ->assertDontSeeText('Staff');
});

it('shows staff-activity-card inside the Staff zone for a staff viewer', function () {
    $officer = officerCommand();
    loginAs($officer);
    $staff = User::factory()
        ->withMembershipLevel(\App\Enums\MembershipLevel::Resident)
        ->withStaffPosition(\App\Enums\StaffDepartment::Engineer, \App\Enums\StaffRank::CrewMember, 'Test Engineer')
        ->withRole('Staff Access')
        ->create();

    $this->get(route('profile.show', $staff))
        ->assertSeeLivewire('users.staff-activity-card');
});

it('does not show staff-activity-card to a non-staff viewer', function () {
    $viewer = User::factory()->create();
    $staff = User::factory()
        ->withMembershipLevel(\App\Enums\MembershipLevel::Resident)
        ->withStaffPosition(\App\Enums\StaffDepartment::Engineer, \App\Enums\StaffRank::CrewMember, 'Test Engineer')
        ->withRole('Staff Access')
        ->create();
    loginAs($viewer);

    $this->get(route('profile.show', $staff))
        ->assertDontSeeLivewire('users.staff-activity-card');
});

it('still shows public cards (display-basic-details, registration-answer-card) to all logged-in users', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create();
    loginAs($viewer);

    $this->get(route('profile.show', $target))
        ->assertSeeLivewire('users.display-basic-details')
        ->assertSeeLivewire('users.registration-answer-card');
});
