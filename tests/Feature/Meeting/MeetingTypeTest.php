<?php

declare(strict_types=1);

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Enums\StaffRank;
use App\Models\Meeting;
use App\Models\User;
use Livewire\Volt\Volt;

it('creates a staff meeting with default type', function () {
    $meeting = Meeting::factory()->create();

    expect($meeting->type)->toBe(MeetingType::StaffMeeting);
    expect($meeting->isStaffMeeting())->toBeTrue();
});

it('creates a meeting with a specific type', function () {
    $meeting = Meeting::factory()->create(['type' => MeetingType::BoardMeeting]);

    expect($meeting->type)->toBe(MeetingType::BoardMeeting);
    expect($meeting->isStaffMeeting())->toBeFalse();
});

it('shows department sections only for staff meetings during in-progress', function () {
    $user = User::factory()->create(['staff_rank' => StaffRank::Officer]);
    $staffMeeting = Meeting::factory()->create([
        'status' => MeetingStatus::InProgress,
        'type' => MeetingType::StaffMeeting,
    ]);

    $component = Volt::actingAs($user)
        ->test('meetings.manage-meeting', ['meeting' => $staffMeeting]);

    $component->assertSee('Chaplain')
        ->assertSee('Command')
        ->assertSee('Engineer');
});

it('shows single general note for non-staff meetings', function () {
    $user = User::factory()->create(['staff_rank' => StaffRank::Officer]);
    $boardMeeting = Meeting::factory()->create([
        'status' => MeetingStatus::InProgress,
        'type' => MeetingType::BoardMeeting,
    ]);

    $component = Volt::actingAs($user)
        ->test('meetings.manage-meeting', ['meeting' => $boardMeeting]);

    $component->assertSee('General')
        ->assertDontSee('Chaplain');
});

it('displays meeting type in meeting details', function () {
    $user = User::factory()->create(['staff_rank' => StaffRank::Officer]);
    $meeting = Meeting::factory()->create([
        'status' => MeetingStatus::Pending,
        'type' => MeetingType::StaffMeeting,
    ]);

    $component = Volt::actingAs($user)
        ->test('meetings.manage-meeting', ['meeting' => $meeting]);

    $component->assertSee('Staff Meeting');
});

it('defaults show_community_updates to true for staff meetings', function () {
    $user = User::factory()->create(['staff_rank' => StaffRank::Officer]);

    Volt::actingAs($user)
        ->test('meeting.create-modal')
        ->set('title', 'Staff Test')
        ->set('day', now()->addDays(7)->format('Y-m-d'))
        ->set('time', '7:00 PM')
        ->set('type', 'staff_meeting')
        ->call('CreateMeeting');

    $meeting = Meeting::latest()->first();
    expect($meeting->show_community_updates)->toBeTrue();
});

it('defaults show_community_updates to false for non-staff meetings', function () {
    $user = User::factory()->create(['staff_rank' => StaffRank::Officer]);

    Volt::actingAs($user)
        ->test('meeting.create-modal')
        ->set('title', 'Board Test')
        ->set('day', now()->addDays(7)->format('Y-m-d'))
        ->set('time', '7:00 PM')
        ->set('type', 'board_meeting')
        ->call('CreateMeeting');

    $meeting = Meeting::latest()->first();
    expect($meeting->show_community_updates)->toBeFalse();
});
