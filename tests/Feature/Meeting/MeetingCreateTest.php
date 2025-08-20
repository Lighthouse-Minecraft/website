<?php

use App\Enums\MembershipLevel;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Meeting;
use App\Models\User;

use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

describe('Meeting Create - Loading', function () {

    it('has a button on the meeting.index page that creates a meeting', function () {
        loginAsAdmin();

        get(route('meeting.index'))
            ->assertSeeLivewire('meeting.create-modal');
    })->done();

})->done(assignee: 'jonzenor', issue: 13);

describe('Meeting Create - Livewire Display Form Component', function () {

    it('loads the modal successfully', function () {
        loginAsAdmin();

        livewire('meeting.create-modal')
            ->assertSeeText('Create a Meeting');
    })->done();

    it('displays the form with required fields', function () {
        loginAsAdmin();

        livewire('meeting.create-modal')
            // Set the form fields data
            ->set('title', 'Test Meeting')
            ->set('day', '2025-04-04')
            ->set('time', '7:00 PM')

            // Make sure the form fields are displayed
            ->assertSee('Create a Meeting')
            ->assertSee('Meeting Title')
            ->assertSee('Meeting Date')

            // Check that the form fields were set correctly
            ->assertSet('title', 'Test Meeting')
            ->assertSet('day', '2025-04-04')
            ->assertSet('time', '7:00 PM')

            // Make sure required components are present
            ->assertSee('data-testid="meeting-create.store"', false);
    })->done();

})->done(assignee: 'jonzenor', issue: 13);

describe('Meeting Create - Functionality', function () {

    it('calculates the scheduled time based on day and time', function () {
        loginAsAdmin();

        livewire('meeting.create-modal')
            ->set('title', 'Test Meeting')
            ->set('day', '2025-04-04')
            ->set('time', '7:00 PM')
            ->call('CreateMeeting')
            ->assertSet('scheduled_time', '2025-04-04 23:00:00');
    })->done();

    it('submits form data without error and saves to the database', function () {
        loginAsAdmin();

        livewire('meeting.create-modal')
            ->set('title', 'Test Meeting')
            ->set('day', '2025-04-04')
            ->set('time', value: '7:00 PM')
            ->call('CreateMeeting')
            ->assertOk();

        $this->assertDatabaseHas('meetings', [
            'title' => 'Test Meeting',
            'day' => '2025-04-04',
            'scheduled_time' => '2025-04-04 23:00:00',
        ]);
    })->done();

    it('validates user input with valid data', function (string $title, string $day, string $time) {
        loginAsAdmin();

        livewire('meeting.create-modal')
            ->set('title', $title)
            ->set('day', $day)
            ->set('time', $time)
            ->call('CreateMeeting')
            ->assertOk();
    })->done()
        ->with([
            ['title' => 'Test Meeting', 'day' => '2025-04-04', 'time' => '7:00 PM'],
            ['title' => 'Test - Meeting', 'day' => '2025-01-01', 'time' => '7:00 AM'],
            ['title' => 'Test\'s Meeting', 'day' => '2025-12-31', 'time' => '12:00 PM'],
            ['title' => '0123857987 Test Meeting', 'day' => '2024-02-29', 'time' => '5:30 PM'],
        ]);

    it('validates user input with invalid data', function ($data, $expectedError) {
        loginAsAdmin();

        livewire('meeting.create-modal')
            ->set('title', $data['title'])
            ->set('day', $data['day'])
            ->set('time', $data['time'])
            ->call('CreateMeeting')
            ->assertHasErrors($expectedError);
    })
        ->done()
        ->with([
            // Requires each field to be set
            [['title' => '', 'day' => '2025-04-04', 'time' => '7:00 PM'], 'title'],
            [['title' => 'Test Meeting', 'day' => '', 'time' => '7:00 PM'], 'day'],
            [['title' => 'Test Meeting', 'day' => '2025-04-04', 'time' => ''], 'time'],

            // Requires each field to be valid type
            [['title' => 'Test Meeting', 'day' => '2025-04-04', 'time' => 'invalid time'], 'time'],
            [['title' => 'Test Meeting', 'day' => 'invalid date', 'time' => '7:00 PM'], 'day'],

            // Requires date and time to be valid
            [['title' => 'Test Meeting', 'day' => 'invalid date', 'time' => '7:00 PM'], 'day'],
            [['title' => 'Test Meeting', 'day' => '2025-04-04', 'time' => '25:00 PM'], 'time'],
            [['title' => 'Test Meeting', 'day' => '2025-04-04', 'time' => '12:00 XM'], 'time'],
            [['title' => 'Test Meeting', 'day' => '2025-04-04', 'time' => '12:00 pm'], 'time'],
            [['title' => 'Test Meeting', 'day' => '2025-04-04', 'time' => '12:00 am'], 'time'],
        ]);

    it('redirects to meeting.show after saving', function () {
        loginAsAdmin();

        livewire('meeting.create-modal')
            ->set('title', 'Test Meeting')
            ->set('day', '2025-04-04')
            ->set('time', '7:00 PM')
            ->call('CreateMeeting')
            ->assertRedirect(route('meeting.edit', ['meeting' => Meeting::latest()->first()]));
    })->done();

})->done(assignee: 'jonzenor', issue: 13);

describe('Meeting Create - Permissions and Security', function () {

    it('allows Officers to access the create meeting page', function ($department) {
        $user = User::factory()->withStaffPosition($department, StaffRank::Officer, 'Officer')->create();
        loginAs($user);

        livewire('meeting.create-modal')
            ->assertSee('Create Meeting');

        livewire('meeting.create-modal')
            ->set('title', 'Test Meeting')
            ->set('day', '2025-04-04')
            ->set('time', '7:00 PM')
            ->call('CreateMeeting')
            ->assertOk();
    })
        ->with([
            StaffDepartment::Command,
            StaffDepartment::Engineer,
            StaffDepartment::Chaplain,
            StaffDepartment::Steward,
            StaffDepartment::Quartermaster,
        ])
        ->done();

    it('denies access to non-staff members', function ($membershipLevel) {
        $user = User::factory()->withMembershipLevel($membershipLevel)->create();
        loginAs($user);
        livewire('meeting.create-modal')
            ->assertForbidden();
    })
        ->with([
            MembershipLevel::Drifter,
            MembershipLevel::Stowaway,
            MembershipLevel::Traveler,
            MembershipLevel::Resident,
            MembershipLevel::Citizen,
        ])
        ->done();

    it('denies access to guests', function () {
        get(route('meeting.index'))
            ->assertDontSeeLivewire('meeting.create-modal');

        livewire('meeting.create-modal')
            ->assertForbidden();
    })->done();

    it('denies access to crew members', function ($staffRank) {
        $user = User::factory()->withStaffPosition(StaffDepartment::Engineer, $staffRank, 'Crew')->create();
        loginAs($user);

        get(route('meeting.index'))
            ->assertDontSeeLivewire('meeting.create-modal');

        livewire('meeting.create-modal')
            ->assertForbidden();
    })
        ->with([
            StaffRank::None,
            StaffRank::JrCrew,
            StaffRank::CrewMember,
        ])
        ->done();

    it('allows users with the Meeting Secretary role to access the create meeting page', function () {
        $user = User::factory()
            ->withRole('Meeting Secretary')
            ->create();
        loginAs($user);

        get(route('meeting.index'))
            ->assertSeeLivewire('meeting.create-modal');

        livewire('meeting.create-modal')
            ->assertSee('Create Meeting');
    })->done();

})->done(assignee: 'jonzenor', issue: 13);
