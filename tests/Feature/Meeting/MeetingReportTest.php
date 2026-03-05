<?php

declare(strict_types=1);

use App\Actions\CreateDefaultMeetingQuestions;
use App\Actions\SubmitMeetingReport;
use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\User;

beforeEach(function () {
    config()->set('lighthouse.meeting_report_unlock_days', 7);
});

it('allows staff to submit a report when unlocked', function () {
    $user = User::factory()->create([
        'staff_rank' => StaffRank::CrewMember,
        'staff_department' => StaffDepartment::Engineer,
    ]);

    $meeting = Meeting::factory()->create([
        'type' => MeetingType::StaffMeeting,
        'status' => MeetingStatus::Pending,
        'scheduled_time' => now()->addDays(3),
    ]);

    CreateDefaultMeetingQuestions::run($meeting);
    $questions = $meeting->questions;

    $answers = [];
    foreach ($questions as $question) {
        $answers[$question->id] = 'Test answer for question '.$question->id;
    }

    $report = SubmitMeetingReport::run($meeting, $user, $answers);

    expect($report->submitted_at)->not->toBeNull();
    expect($report->answers)->toHaveCount(4);
    expect($report->user_id)->toBe($user->id);
});

it('does not allow report submission for non-staff meetings', function () {
    $user = User::factory()->create(['staff_rank' => StaffRank::CrewMember]);
    $meeting = Meeting::factory()->create([
        'type' => MeetingType::BoardMeeting,
        'status' => MeetingStatus::Pending,
    ]);

    SubmitMeetingReport::run($meeting, $user, []);
})->throws(\InvalidArgumentException::class, 'Reports can only be submitted for staff meetings.');

it('does not allow report submission after meeting starts', function () {
    $user = User::factory()->create(['staff_rank' => StaffRank::CrewMember]);
    $meeting = Meeting::factory()->create([
        'type' => MeetingType::StaffMeeting,
        'status' => MeetingStatus::InProgress,
    ]);

    SubmitMeetingReport::run($meeting, $user, []);
})->throws(\InvalidArgumentException::class, 'Reports cannot be submitted after the meeting has started.');

it('allows updating an existing report', function () {
    $user = User::factory()->create([
        'staff_rank' => StaffRank::CrewMember,
        'staff_department' => StaffDepartment::Engineer,
    ]);

    $meeting = Meeting::factory()->create([
        'type' => MeetingType::StaffMeeting,
        'status' => MeetingStatus::Pending,
        'scheduled_time' => now()->addDays(3),
    ]);

    CreateDefaultMeetingQuestions::run($meeting);
    $questions = $meeting->questions;

    $answers = [];
    foreach ($questions as $question) {
        $answers[$question->id] = 'First answer';
    }

    SubmitMeetingReport::run($meeting, $user, $answers);

    $updatedAnswers = [];
    foreach ($questions as $question) {
        $updatedAnswers[$question->id] = 'Updated answer';
    }

    $report = SubmitMeetingReport::run($meeting, $user, $updatedAnswers);

    expect(MeetingReport::where('meeting_id', $meeting->id)->where('user_id', $user->id)->count())->toBe(1);
    expect($report->answers->first()->answer)->toBe('Updated answer');
});

it('report is unlocked within the configured window', function () {
    $meeting = Meeting::factory()->create([
        'type' => MeetingType::StaffMeeting,
        'status' => MeetingStatus::Pending,
        'scheduled_time' => now()->addDays(5),
    ]);

    expect($meeting->isReportUnlocked())->toBeTrue();
});

it('report is not unlocked outside the configured window', function () {
    $meeting = Meeting::factory()->create([
        'type' => MeetingType::StaffMeeting,
        'status' => MeetingStatus::Pending,
        'scheduled_time' => now()->addDays(14),
    ]);

    expect($meeting->isReportUnlocked())->toBeFalse();
});

it('report is locked when meeting has started', function () {
    $meeting = Meeting::factory()->create([
        'type' => MeetingType::StaffMeeting,
        'status' => MeetingStatus::InProgress,
    ]);

    expect($meeting->isReportLocked())->toBeTrue();
    expect($meeting->isReportUnlocked())->toBeFalse();
});
