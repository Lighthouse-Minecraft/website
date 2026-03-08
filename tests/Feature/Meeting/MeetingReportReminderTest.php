<?php

declare(strict_types=1);

use App\Actions\CreateDefaultMeetingQuestions;
use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\User;
use App\Notifications\MeetingReportReminderNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config()->set('lighthouse.meeting_report_notify_days', 3);
});

it('sends reminders to staff who have not submitted reports', function () {
    Notification::fake();

    $staffUser = User::factory()->create([
        'staff_rank' => StaffRank::CrewMember,
        'staff_department' => StaffDepartment::Engineer,
    ]);

    $meeting = Meeting::factory()->create([
        'type' => MeetingType::StaffMeeting,
        'status' => MeetingStatus::Pending,
        'scheduled_time' => now()->addDays(2),
    ]);

    CreateDefaultMeetingQuestions::run($meeting);

    $this->artisan('meetings:send-report-reminders')
        ->assertSuccessful();

    $report = MeetingReport::where('meeting_id', $meeting->id)
        ->where('user_id', $staffUser->id)
        ->first();

    expect($report)->not->toBeNull();
    expect($report->notified_at)->not->toBeNull();
    expect($report->submitted_at)->toBeNull();

    Notification::assertSentTo($staffUser, MeetingReportReminderNotification::class);
});

it('does not send reminders to staff who already submitted', function () {
    Notification::fake();

    $staffUser = User::factory()->create([
        'staff_rank' => StaffRank::CrewMember,
        'staff_department' => StaffDepartment::Engineer,
    ]);

    $meeting = Meeting::factory()->create([
        'type' => MeetingType::StaffMeeting,
        'status' => MeetingStatus::Pending,
        'scheduled_time' => now()->addDays(2),
    ]);

    CreateDefaultMeetingQuestions::run($meeting);

    MeetingReport::create([
        'meeting_id' => $meeting->id,
        'user_id' => $staffUser->id,
        'submitted_at' => now(),
    ]);

    $this->artisan('meetings:send-report-reminders')
        ->assertSuccessful();

    Notification::assertNothingSent();
});

it('does not send reminders outside the notification window', function () {
    Notification::fake();

    User::factory()->create([
        'staff_rank' => StaffRank::CrewMember,
        'staff_department' => StaffDepartment::Engineer,
    ]);

    $meeting = Meeting::factory()->create([
        'type' => MeetingType::StaffMeeting,
        'status' => MeetingStatus::Pending,
        'scheduled_time' => now()->addDays(10),
    ]);

    CreateDefaultMeetingQuestions::run($meeting);

    $this->artisan('meetings:send-report-reminders')
        ->assertSuccessful();

    Notification::assertNothingSent();
});

it('does not send duplicate reminders', function () {
    Notification::fake();

    $staffUser = User::factory()->create([
        'staff_rank' => StaffRank::CrewMember,
        'staff_department' => StaffDepartment::Engineer,
    ]);

    $meeting = Meeting::factory()->create([
        'type' => MeetingType::StaffMeeting,
        'status' => MeetingStatus::Pending,
        'scheduled_time' => now()->addDays(2),
    ]);

    CreateDefaultMeetingQuestions::run($meeting);

    MeetingReport::create([
        'meeting_id' => $meeting->id,
        'user_id' => $staffUser->id,
        'notified_at' => now()->subDay(),
    ]);

    $this->artisan('meetings:send-report-reminders')
        ->assertSuccessful();

    Notification::assertNothingSent();
});
