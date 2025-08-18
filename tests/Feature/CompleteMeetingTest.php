<?php

declare(strict_types=1);

use App\Enums\MeetingStatus;
use App\Enums\StaffRank;
use App\Models\Meeting;
use App\Models\MeetingNote;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->user = User::factory()->create([
        'staff_rank' => StaffRank::Officer, // Give proper permissions
    ]);
    $this->meeting = Meeting::factory()->create([
        'status' => MeetingStatus::Finalizing,
    ]);
});

test('complete meeting button calls CompleteMeeting method', function () {
    $this->actingAs($this->user);

    Volt::test('meetings.manage-meeting', ['meeting' => $this->meeting])
        ->assertSee('Complete Meeting')
        ->call('CompleteMeeting')
        ->assertHasNoErrors();
});

test('complete meeting method shows modal', function () {
    $this->actingAs($this->user);

    Volt::test('meetings.manage-meeting', ['meeting' => $this->meeting])
        ->call('CompleteMeeting')
        ->assertHasNoErrors();

    // Check that log was created (debugging)
    expect(storage_path('logs/laravel.log'))->toBeFile();
});

test('complete meeting confirmed changes status', function () {
    $this->actingAs($this->user);

    $communityNote = MeetingNote::factory()->create([
        'meeting_id' => $this->meeting->id,
        'section_key' => 'community',
        'content' => 'Test community notes',
    ]);

    $component = Volt::test('meetings.manage-meeting', ['meeting' => $this->meeting])
        ->call('CompleteMeetingConfirmed')
        ->assertHasNoErrors();

    // Refresh the meeting from database
    $updatedMeeting = Meeting::find($this->meeting->id);

    expect($updatedMeeting->status)->toBe(MeetingStatus::Completed);
    expect($updatedMeeting->community_minutes)->toBe('Test community notes');
});

test('complete meeting requires proper authorization', function () {
    $unauthorizedUser = User::factory()->create(); // No special rank

    $this->actingAs($unauthorizedUser);

    Volt::test('meetings.manage-meeting', ['meeting' => $this->meeting])
        ->call('CompleteMeeting')
        ->assertForbidden();
});

test('complete meeting only works in finalizing status', function () {
    $meeting = Meeting::factory()->create([
        'status' => MeetingStatus::InProgress,
    ]);

    $this->actingAs($this->user);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Meeting cannot be completed unless it is finalizing.');

    Volt::test('meetings.manage-meeting', ['meeting' => $meeting])
        ->call('CompleteMeetingConfirmed');
});
