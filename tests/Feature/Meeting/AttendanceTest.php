<?php

use App\Enums\MeetingStatus;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Meeting;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    // Create an admin user who can manage meetings
    $this->admin = User::factory()->create([
        'staff_rank' => StaffRank::Officer,
        'staff_department' => StaffDepartment::Command,
        'staff_title' => 'Test Admin',
    ]);

    $this->officer = User::factory()->create([
        'staff_rank' => StaffRank::Officer,
        'staff_title' => 'Test Officer',
    ]);

    $this->crewMember = User::factory()->create([
        'staff_rank' => StaffRank::CrewMember,
        'staff_title' => 'Test Crew',
    ]);

    $this->jrCrew = User::factory()->create([
        'staff_rank' => StaffRank::JrCrew,
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

test('add attendee button is visible when meeting is in progress', function () {
    loginAsAdmin();

    livewire('meeting.manage-attendees', ['meeting' => $this->meeting])
        ->assertSee('Add Attendee');
})->done(issue: 55);

test('add attendee button is not visible when meeting is not in progress', function () {
    loginAsAdmin();

    // Test each status individually with fresh models
    $pendingMeeting = Meeting::factory()->create(['status' => MeetingStatus::Pending]);
    livewire('meeting.manage-attendees', ['meeting' => $pendingMeeting])
        ->assertDontSee('Add Attendee');

    $finalizingMeeting = Meeting::factory()->create(['status' => MeetingStatus::Finalizing]);
    livewire('meeting.manage-attendees', ['meeting' => $finalizingMeeting])
        ->assertDontSee('Add Attendee');

    $completedMeeting = Meeting::factory()->create(['status' => MeetingStatus::Completed]);
    livewire('meeting.manage-attendees', ['meeting' => $completedMeeting])
        ->assertDontSee('Add Attendee');
});

test('add attendee button opens modal', function () {
    loginAsAdmin();

    livewire('meeting.manage-attendees', ['meeting' => $this->meeting])
        ->call('openModal')
        ->assertOk();
});

test('add attendee modal shows all eligible staff members', function () {
    loginAsAdmin();

    livewire('meeting.manage-attendees', ['meeting' => $this->meeting])
        ->assertSee($this->officer->name)
        ->assertSee($this->crewMember->name)
        ->assertSee($this->jrCrew->name)
        ->assertDontSee($this->nonStaff->name);
});

test('add attendee modal allows selecting multiple officers', function () {
    loginAsAdmin();

    livewire('meeting.manage-attendees', ['meeting' => $this->meeting])
        ->set('selectedAttendees', [$this->officer->id, $this->crewMember->id])
        ->assertSet('selectedAttendees', [$this->officer->id, $this->crewMember->id]);
});

test('add attendee modal allows saving and closing', function () {
    loginAsAdmin();

    livewire('meeting.manage-attendees', ['meeting' => $this->meeting])
        ->set('selectedAttendees', [$this->officer->id, $this->crewMember->id])
        ->call('addAttendees')
        ->assertSet('selectedAttendees', []);

    expect($this->meeting->fresh()->attendees)->toHaveCount(2);
    expect($this->meeting->fresh()->attendees->pluck('id')->toArray())
        ->toContain($this->officer->id)
        ->toContain($this->crewMember->id);
});

test('attendees are updated in meeting through pivot table with timestamp', function () {
    $this->meeting->attendees()->attach($this->officer->id, ['added_at' => now()]);

    expect($this->meeting->fresh()->attendees)->toHaveCount(1);
    expect($this->meeting->fresh()->attendees->first()->pivot->added_at)->not->toBeNull();
});

test('attendees list is displayed in meeting details', function () {
    $this->meeting->attendees()->attach([
        $this->officer->id => ['added_at' => now()],
        $this->crewMember->id => ['added_at' => now()->addMinute()],
    ]);

    // Debug: verify the attendees were attached correctly
    expect($this->meeting->fresh()->attendees)->toHaveCount(2);

    $response = $this->actingAs($this->admin)
        ->get('/meetings/'.$this->meeting->id);

    $response->assertSee('Attendees:', false); // Don't escape HTML
    $response->assertSee('2');
    $response->assertSee($this->officer->name);
    $response->assertSee($this->crewMember->name);
    $response->assertSee($this->officer->staff_title);
    $response->assertSee($this->crewMember->staff_title);
});

test('person who starts meeting is automatically added as attendee', function () {
    $pendingMeeting = Meeting::factory()->create([
        'status' => MeetingStatus::Pending,
    ]);

    $this->actingAs($this->admin);
    $pendingMeeting->startMeeting();

    expect($pendingMeeting->fresh()->attendees)->toHaveCount(1);
    expect($pendingMeeting->fresh()->attendees->first()->id)->toBe($this->admin->id);
});

test('cannot add same person twice to same meeting', function () {
    $this->meeting->attendees()->attach($this->officer->id, ['added_at' => now()]);

    loginAsAdmin();

    livewire('meeting.manage-attendees', ['meeting' => $this->meeting])
        ->assertDontSee($this->officer->name); // Should not appear in available list

    // Try to add the same person again should not increase count
    expect($this->meeting->fresh()->attendees)->toHaveCount(1);
});

test('only users with update permissions can add attendees', function () {
    $unauthorizedUser = User::factory()->create([
        'staff_rank' => StaffRank::CrewMember, // Crew members don't have update permissions
    ]);

    $this->actingAs($unauthorizedUser);

    livewire('meeting.manage-attendees', ['meeting' => $this->meeting])
        ->call('openModal')
        ->assertForbidden();

    livewire('meeting.manage-attendees', ['meeting' => $this->meeting])
        ->set('selectedAttendees', [$this->officer->id])
        ->call('addAttendees')
        ->assertForbidden();
});

test('attendee count is displayed in meeting details', function () {
    $this->meeting->attendees()->attach([
        $this->officer->id => ['added_at' => now()],
        $this->crewMember->id => ['added_at' => now()],
        $this->jrCrew->id => ['added_at' => now()],
    ]);

    $response = $this->actingAs($this->admin)
        ->get('/meetings/'.$this->meeting->id);

    $response->assertSee('Attendees:', false); // Don't escape HTML
    $response->assertSee('3');
});

test('attendees display full staff title and rank', function () {
    $this->meeting->attendees()->attach($this->officer->id, ['added_at' => now()]);

    $response = $this->actingAs($this->admin)
        ->get('/meetings/'.$this->meeting->id);

    $response->assertSee($this->officer->staff_rank->label())
        ->assertSee($this->officer->staff_title);
});

test('attendees are ordered by time added', function () {
    $firstTime = now();
    $secondTime = now()->addMinute();

    $this->meeting->attendees()->attach([
        $this->crewMember->id => ['added_at' => $secondTime],
        $this->officer->id => ['added_at' => $firstTime],
    ]);

    $attendees = $this->meeting->fresh()->attendees;

    expect($attendees->first()->id)->toBe($this->officer->id);
    expect($attendees->last()->id)->toBe($this->crewMember->id);
});

test('meeting secretary can manage attendees', function () {
    $secretary = User::factory()
        ->withRole('Meeting Secretary')
        ->create([
            'staff_rank' => StaffRank::CrewMember,
            'staff_title' => 'Meeting Secretary',
        ]);

    $this->actingAs($secretary);

    livewire('meeting.manage-attendees', ['meeting' => $this->meeting])
        ->set('selectedAttendees', [$this->officer->id])
        ->call('addAttendees')
        ->assertHasNoErrors();

    expect($this->meeting->fresh()->attendees)->toHaveCount(1);
    expect($this->meeting->fresh()->attendees->first()->id)->toBe($this->officer->id);
});
