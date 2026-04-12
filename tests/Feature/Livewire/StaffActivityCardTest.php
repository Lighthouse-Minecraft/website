<?php

declare(strict_types=1);

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Enums\MembershipLevel;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Enums\ThreadStatus;
use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\Thread;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('staff-activity', 'livewire');

// Helpers
function staffMemberWithPosition(): User
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Engineer, StaffRank::CrewMember, 'Test Engineer')
        ->withRole('Staff Access')
        ->create();
}

function completedStaffMeeting(array $attributes = []): Meeting
{
    return Meeting::factory()->create(array_merge([
        'type' => MeetingType::StaffMeeting,
        'status' => MeetingStatus::Completed,
        'end_time' => now()->subWeek(),
        'scheduled_time' => now()->subWeek(),
    ], $attributes));
}

// Authorization tests

it('shows card on staff profile page to the staff member themselves', function () {
    $staff = staffMemberWithPosition();
    loginAs($staff);

    $this->get(route('profile.show', $staff))
        ->assertSeeLivewire('users.staff-activity-card');
});

it('shows card on staff profile page to an officer', function () {
    $officer = officerCommand();
    loginAs($officer);
    $staff = staffMemberWithPosition();

    $this->get(route('profile.show', $staff))
        ->assertSeeLivewire('users.staff-activity-card');
});

it('does not show card on non-staff profile page', function () {
    $officer = officerCommand();
    loginAs($officer);
    $regular = membershipTraveler();

    $this->get(route('profile.show', $regular))
        ->assertDontSeeLivewire('users.staff-activity-card');
});

it('denies crew members from viewing another staff member activity card', function () {
    $crew = crewEngineer();
    loginAs($crew);
    $staff = staffMemberWithPosition();

    Volt::test('users.staff-activity-card', ['user' => $staff])
        ->assertForbidden();
});

it('denies regular members from viewing the staff activity card', function () {
    $regular = membershipTraveler();
    loginAs($regular);
    $staff = staffMemberWithPosition();

    Volt::test('users.staff-activity-card', ['user' => $staff])
        ->assertForbidden();
});

// Meeting table row tests

it('shows attended badge for a meeting the staff member attended', function () {
    $staff = staffMemberWithPosition();
    loginAs($staff);

    $meeting = completedStaffMeeting(['title' => 'Weekly Standup']);
    $meeting->attendees()->attach($staff->id, ['added_at' => now(), 'attended' => true]);

    Volt::test('users.staff-activity-card', ['user' => $staff])
        ->assertSee('Weekly Standup')
        ->assertSee('Attended');
});

it('shows absent badge for a meeting the staff member missed', function () {
    $staff = staffMemberWithPosition();
    loginAs($staff);

    $meeting = completedStaffMeeting(['title' => 'Weekly Standup']);
    $meeting->attendees()->attach($staff->id, ['added_at' => now(), 'attended' => false]);

    Volt::test('users.staff-activity-card', ['user' => $staff])
        ->assertSee('Weekly Standup')
        ->assertSee('Absent');
});

it('shows not on record badge when staff member has no attendance entry', function () {
    $staff = staffMemberWithPosition();
    loginAs($staff);

    completedStaffMeeting(['title' => 'Weekly Standup']);

    Volt::test('users.staff-activity-card', ['user' => $staff])
        ->assertSee('Weekly Standup')
        ->assertSee('Not on Record');
});

it('shows view report button when a submitted report exists for the meeting', function () {
    $staff = staffMemberWithPosition();
    loginAs($staff);

    $meeting = completedStaffMeeting();
    MeetingReport::create([
        'meeting_id' => $meeting->id,
        'user_id' => $staff->id,
        'submitted_at' => now(),
    ]);

    Volt::test('users.staff-activity-card', ['user' => $staff])
        ->assertSee('View Report');
});

it('does not show view report button when no report was submitted', function () {
    $staff = staffMemberWithPosition();
    loginAs($staff);

    completedStaffMeeting();

    Volt::test('users.staff-activity-card', ['user' => $staff])
        ->assertDontSee('View Report');
});

// Ticket count tests

it('shows correct open and closed ticket counts', function () {
    $staff = staffMemberWithPosition();
    loginAs($staff);

    Thread::factory()->assigned($staff)->create(['status' => ThreadStatus::Open]);
    Thread::factory()->assigned($staff)->create(['status' => ThreadStatus::Pending]);
    Thread::factory()->assigned($staff)->create(['status' => ThreadStatus::Closed]);
    Thread::factory()->assigned($staff)->create(['status' => ThreadStatus::Resolved]);

    Volt::test('users.staff-activity-card', ['user' => $staff])
        ->assertSee('2 open')
        ->assertSee('2 closed');
});

it('shows zero ticket counts when no tickets assigned', function () {
    $staff = staffMemberWithPosition();
    loginAs($staff);

    Volt::test('users.staff-activity-card', ['user' => $staff])
        ->assertSee('0 open')
        ->assertSee('0 closed');
});
