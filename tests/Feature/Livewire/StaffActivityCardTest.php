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

// Attendance count tests

it('shows correct attendance count from last 3 months', function () {
    $staff = staffMemberWithPosition();
    loginAs($staff);

    $attended = completedStaffMeeting();
    $missed = completedStaffMeeting();
    $attended->attendees()->attach($staff->id, ['added_at' => now(), 'attended' => true]);
    $missed->attendees()->attach($staff->id, ['added_at' => now(), 'attended' => false]);

    Volt::test('users.staff-activity-card', ['user' => $staff])
        ->assertSee('1 / 2');
});

it('does not count meetings older than 3 months in attendance', function () {
    $staff = staffMemberWithPosition();
    loginAs($staff);

    $old = completedStaffMeeting(['end_time' => now()->subMonths(4), 'scheduled_time' => now()->subMonths(4)]);
    $old->attendees()->attach($staff->id, ['added_at' => now(), 'attended' => true]);

    Volt::test('users.staff-activity-card', ['user' => $staff])
        ->assertSee('0 / 0');
});

// Reports filed/missed tests

it('shows correct reports filed and missed count', function () {
    $staff = staffMemberWithPosition();
    loginAs($staff);

    $meeting1 = completedStaffMeeting();
    $meeting2 = completedStaffMeeting();

    MeetingReport::create([
        'meeting_id' => $meeting1->id,
        'user_id' => $staff->id,
        'submitted_at' => now(),
    ]);
    // No report for meeting2

    Volt::test('users.staff-activity-card', ['user' => $staff])
        ->assertSee('1 filed')
        ->assertSee('1 missed');
});

it('does not show missed badge when all reports are filed', function () {
    $staff = staffMemberWithPosition();
    loginAs($staff);

    $meeting = completedStaffMeeting();
    MeetingReport::create([
        'meeting_id' => $meeting->id,
        'user_id' => $staff->id,
        'submitted_at' => now(),
    ]);

    Volt::test('users.staff-activity-card', ['user' => $staff])
        ->assertSee('1 filed')
        ->assertDontSee('missed');
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
