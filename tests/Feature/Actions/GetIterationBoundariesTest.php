<?php

use App\Actions\GetIterationBoundaries;
use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Models\Meeting;
use Illuminate\Support\Facades\Cache;

uses()->group('command-dashboard', 'actions');

beforeEach(function () {
    Cache::flush();
});

it('returns fallback boundaries when no completed staff meetings exist', function () {
    $result = GetIterationBoundaries::run();

    expect($result['current_start'])->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($result['current_end'])->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($result['current_meeting'])->toBeNull()
        ->and($result['previous_start'])->toBeNull()
        ->and($result['previous_end'])->toBeNull()
        ->and($result['previous_meeting'])->toBeNull()
        ->and($result['has_previous'])->toBeFalse()
        ->and($result['iterations_3mo'])->toBeEmpty();
});

it('computes current iteration from last completed staff meeting to now', function () {
    $meeting = Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
        'type' => MeetingType::StaffMeeting,
        'end_time' => now()->subDays(7),
    ]);

    $result = GetIterationBoundaries::run();

    expect($result['current_start']->toDateString())->toBe($meeting->end_time->toDateString())
        ->and($result['current_end']->toDateString())->toBe(now()->toDateString())
        ->and($result['has_previous'])->toBeFalse()
        ->and($result['previous_meeting'])->toBeNull();
});

it('computes previous iteration between second-to-last and last meetings', function () {
    $meeting1 = Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
        'type' => MeetingType::StaffMeeting,
        'end_time' => now()->subDays(28),
    ]);
    $meeting2 = Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
        'type' => MeetingType::StaffMeeting,
        'end_time' => now()->subDays(14),
    ]);

    $result = GetIterationBoundaries::run();

    expect($result['has_previous'])->toBeTrue()
        ->and($result['previous_start']->toDateString())->toBe($meeting1->end_time->toDateString())
        ->and($result['previous_end']->toDateString())->toBe($meeting2->end_time->toDateString())
        ->and($result['previous_meeting']->id)->toBe($meeting2->id)
        ->and($result['current_start']->toDateString())->toBe($meeting2->end_time->toDateString());
});

it('builds iterations_3mo array for completed meetings within 3 months', function () {
    Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
        'type' => MeetingType::StaffMeeting,
        'end_time' => now()->subDays(42),
    ]);
    Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
        'type' => MeetingType::StaffMeeting,
        'end_time' => now()->subDays(28),
    ]);
    Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
        'type' => MeetingType::StaffMeeting,
        'end_time' => now()->subDays(14),
    ]);

    $result = GetIterationBoundaries::run();

    expect($result['iterations_3mo'])->toHaveCount(2);
});

it('only considers staff meetings, not board or community meetings', function () {
    Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
        'type' => MeetingType::BoardMeeting,
        'end_time' => now()->subDays(14),
    ]);
    Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
        'type' => MeetingType::CommunityMeeting,
        'end_time' => now()->subDays(7),
    ]);

    $result = GetIterationBoundaries::run();

    // Should fall back to 30-day window since no staff meetings exist
    expect($result['has_previous'])->toBeFalse()
        ->and($result['current_meeting'])->toBeNull()
        ->and($result['previous_meeting'])->toBeNull();
});

it('only considers completed meetings, not pending or cancelled', function () {
    Meeting::factory()->withStatus(MeetingStatus::Pending)->create([
        'type' => MeetingType::StaffMeeting,
        'end_time' => now()->subDays(14),
    ]);
    Meeting::factory()->withStatus(MeetingStatus::Cancelled)->create([
        'type' => MeetingType::StaffMeeting,
        'end_time' => now()->subDays(7),
    ]);

    $result = GetIterationBoundaries::run();

    expect($result['has_previous'])->toBeFalse()
        ->and($result['previous_meeting'])->toBeNull();
});

it('caches results', function () {
    Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
        'type' => MeetingType::StaffMeeting,
        'end_time' => now()->subDays(14),
    ]);

    $result1 = GetIterationBoundaries::run();

    // Create another meeting — should not affect cached result
    Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
        'type' => MeetingType::StaffMeeting,
        'end_time' => now()->subDays(7),
    ]);

    $result2 = GetIterationBoundaries::run();

    expect($result2['has_previous'])->toBe($result1['has_previous']);
});
