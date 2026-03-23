<?php

use App\Enums\MeetingStatus;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Meeting;
use App\Models\User;

use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->admin = User::factory()->withRole('Meeting - Manager')->create([
        'staff_rank' => StaffRank::Officer,
        'staff_department' => StaffDepartment::Command,
        'staff_title' => 'Test Admin',
    ]);

    $this->officer = User::factory()->create([
        'staff_rank' => StaffRank::Officer,
        'staff_department' => StaffDepartment::Chaplain,
        'staff_title' => 'Test Officer',
    ]);

    $this->crewMember = User::factory()->create([
        'staff_rank' => StaffRank::CrewMember,
        'staff_department' => StaffDepartment::Engineer,
        'staff_title' => 'Test Crew',
    ]);

    $this->jrCrew = User::factory()->create([
        'staff_rank' => StaffRank::JrCrew,
        'staff_department' => StaffDepartment::Steward,
        'staff_title' => 'Test Jr Crew',
    ]);

    $this->nonStaff = User::factory()->create([
        'staff_rank' => StaffRank::None,
    ]);

    $this->meeting = Meeting::factory()->create([
        'status' => MeetingStatus::InProgress,
        'start_time' => now(),
    ]);
});

test('seeds all staff as absent when meeting starts', function () {
    $pendingMeeting = Meeting::factory()->create([
        'status' => MeetingStatus::Pending,
    ]);

    $this->actingAs($this->admin);
    $pendingMeeting->startMeeting();

    $attendees = $pendingMeeting->fresh()->attendees;

    // All staff (CrewMember+) should have records
    expect($attendees)->toHaveCount(3); // admin, officer, crewMember
    expect($attendees->pluck('id')->toArray())
        ->toContain($this->admin->id)
        ->toContain($this->officer->id)
        ->toContain($this->crewMember->id)
        ->not->toContain($this->jrCrew->id)
        ->not->toContain($this->nonStaff->id);
});

test('marks the meeting starter as present', function () {
    $pendingMeeting = Meeting::factory()->create([
        'status' => MeetingStatus::Pending,
    ]);

    $this->actingAs($this->admin);
    $pendingMeeting->startMeeting();

    $attendees = $pendingMeeting->fresh()->attendees;

    // Starter should be present
    $starter = $attendees->firstWhere('id', $this->admin->id);
    expect((bool) $starter->pivot->attended)->toBeTrue();

    // Others should be absent
    $other = $attendees->firstWhere('id', $this->officer->id);
    expect((bool) $other->pivot->attended)->toBeFalse();
});

test('does not seed non-staff users', function () {
    $pendingMeeting = Meeting::factory()->create([
        'status' => MeetingStatus::Pending,
    ]);

    $this->actingAs($this->admin);
    $pendingMeeting->startMeeting();

    $attendeeIds = $pendingMeeting->fresh()->attendees->pluck('id')->toArray();
    expect($attendeeIds)->not->toContain($this->nonStaff->id);
});

test('shows manage attendees button during in_progress', function () {
    loginAsAdmin();

    livewire('meeting.manage-attendees', ['meeting' => $this->meeting])
        ->assertSee('Manage Attendees');
})->done(issue: 55);

test('shows manage attendees button during finalizing', function () {
    loginAsAdmin();

    $finalizingMeeting = Meeting::factory()->create([
        'status' => MeetingStatus::Finalizing,
        'start_time' => now()->subHour(),
        'end_time' => now(),
    ]);

    livewire('meeting.manage-attendees', ['meeting' => $finalizingMeeting])
        ->assertSee('Manage Attendees');
});

test('does not show manage attendees button when completed', function () {
    loginAsAdmin();

    $completedMeeting = Meeting::factory()->create(['status' => MeetingStatus::Completed]);
    livewire('meeting.manage-attendees', ['meeting' => $completedMeeting])
        ->assertDontSee('Manage Attendees');
});

test('does not show manage attendees button when pending', function () {
    loginAsAdmin();

    $pendingMeeting = Meeting::factory()->create(['status' => MeetingStatus::Pending]);
    livewire('meeting.manage-attendees', ['meeting' => $pendingMeeting])
        ->assertDontSee('Manage Attendees');
});

test('toggles attendance from absent to present', function () {
    loginAsAdmin();

    $this->meeting->attendees()->attach($this->officer->id, [
        'added_at' => now(),
        'attended' => false,
    ]);

    livewire('meeting.manage-attendees', ['meeting' => $this->meeting])
        ->call('toggleAttendance', $this->officer->id)
        ->assertHasNoErrors();

    $pivot = $this->meeting->attendees()->where('users.id', $this->officer->id)->first()->pivot;
    expect((bool) $pivot->attended)->toBeTrue();
});

test('toggles attendance from present to absent', function () {
    loginAsAdmin();

    $this->meeting->attendees()->attach($this->officer->id, [
        'added_at' => now(),
        'attended' => true,
    ]);

    livewire('meeting.manage-attendees', ['meeting' => $this->meeting])
        ->call('toggleAttendance', $this->officer->id)
        ->assertHasNoErrors();

    $pivot = $this->meeting->attendees()->where('users.id', $this->officer->id)->first()->pivot;
    expect((bool) $pivot->attended)->toBeFalse();
});

test('mark all present sets all attendees to present', function () {
    loginAsAdmin();

    $this->meeting->attendees()->attach([
        $this->officer->id => ['added_at' => now(), 'attended' => false],
        $this->crewMember->id => ['added_at' => now(), 'attended' => false],
    ]);

    livewire('meeting.manage-attendees', ['meeting' => $this->meeting])
        ->call('markAllPresent')
        ->assertHasNoErrors();

    $attendees = $this->meeting->fresh()->attendees;
    $attendees->each(function ($attendee) {
        expect((bool) $attendee->pivot->attended)->toBeTrue();
    });
});

test('mark all absent sets all attendees to absent', function () {
    loginAsAdmin();

    $this->meeting->attendees()->attach([
        $this->officer->id => ['added_at' => now(), 'attended' => true],
        $this->crewMember->id => ['added_at' => now(), 'attended' => true],
    ]);

    livewire('meeting.manage-attendees', ['meeting' => $this->meeting])
        ->call('markAllAbsent')
        ->assertHasNoErrors();

    $attendees = $this->meeting->fresh()->attendees;
    $attendees->each(function ($attendee) {
        expect((bool) $attendee->pivot->attended)->toBeFalse();
    });
});

test('only authorized users can toggle attendance', function () {
    $unauthorizedUser = User::factory()->create([
        'staff_rank' => StaffRank::CrewMember,
    ]);

    $this->meeting->attendees()->attach($this->officer->id, [
        'added_at' => now(),
        'attended' => false,
    ]);

    $this->actingAs($unauthorizedUser);

    livewire('meeting.manage-attendees', ['meeting' => $this->meeting])
        ->call('toggleAttendance', $this->officer->id)
        ->assertForbidden();

    livewire('meeting.manage-attendees', ['meeting' => $this->meeting])
        ->call('markAllPresent')
        ->assertForbidden();

    livewire('meeting.manage-attendees', ['meeting' => $this->meeting])
        ->call('markAllAbsent')
        ->assertForbidden();

    livewire('meeting.manage-attendees', ['meeting' => $this->meeting])
        ->call('openModal')
        ->assertForbidden();
});

test('user with Meeting - Manager role can manage attendees', function () {
    $secretary = User::factory()
        ->withRole('Meeting - Manager')
        ->create([
            'staff_rank' => StaffRank::CrewMember,
            'staff_title' => 'Meeting Manager',
        ]);

    $this->meeting->attendees()->attach($this->officer->id, [
        'added_at' => now(),
        'attended' => false,
    ]);

    $this->actingAs($secretary);

    livewire('meeting.manage-attendees', ['meeting' => $this->meeting])
        ->call('toggleAttendance', $this->officer->id)
        ->assertHasNoErrors();

    $pivot = $this->meeting->attendees()->where('users.id', $this->officer->id)->first()->pivot;
    expect((bool) $pivot->attended)->toBeTrue();
});

test('attendee count shows present vs total in meeting details', function () {
    $this->meeting->attendees()->attach([
        $this->officer->id => ['added_at' => now(), 'attended' => true],
        $this->crewMember->id => ['added_at' => now(), 'attended' => false],
        $this->jrCrew->id => ['added_at' => now(), 'attended' => true],
    ]);

    loginAsAdmin();

    get(route('meeting.edit', $this->meeting))
        ->assertSee('Attendance:')
        ->assertSee('2 / 3 present');
});

test('attendee list shows present and absent indicators', function () {
    $this->meeting->attendees()->attach([
        $this->officer->id => ['added_at' => now(), 'attended' => true],
        $this->crewMember->id => ['added_at' => now(), 'attended' => false],
    ]);

    loginAsAdmin();

    get(route('meeting.edit', $this->meeting))
        ->assertSee($this->officer->name)
        ->assertSee($this->crewMember->name)
        ->assertSee('Absent');
});

test('attendees are ordered by time added', function () {
    $firstTime = now();
    $secondTime = now()->addMinute();

    $this->meeting->attendees()->attach([
        $this->crewMember->id => ['added_at' => $secondTime, 'attended' => true],
        $this->officer->id => ['added_at' => $firstTime, 'attended' => true],
    ]);

    $attendees = $this->meeting->fresh()->attendees;

    expect($attendees->first()->id)->toBe($this->officer->id);
    expect($attendees->last()->id)->toBe($this->crewMember->id);
});
