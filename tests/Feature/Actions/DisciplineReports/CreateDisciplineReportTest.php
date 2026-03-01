<?php

declare(strict_types=1);

use App\Actions\CreateDisciplineReport;
use App\Enums\ReportLocation;
use App\Enums\ReportSeverity;
use App\Enums\ReportStatus;
use App\Models\DisciplineReport;
use App\Models\User;
use App\Notifications\DisciplineReportPendingReviewNotification;
use Illuminate\Support\Facades\Notification;

uses()->group('discipline-reports', 'actions');

it('creates a draft discipline report', function () {
    $reporter = officerCommand();
    $subject = User::factory()->create();

    $report = CreateDisciplineReport::run(
        $subject,
        $reporter,
        'Test incident description',
        ReportLocation::Minecraft,
        'Verbal warning given',
        ReportSeverity::Minor,
        'PlayerA, PlayerB',
    );

    expect($report)->toBeInstanceOf(DisciplineReport::class)
        ->and($report->subject_user_id)->toBe($subject->id)
        ->and($report->reporter_user_id)->toBe($reporter->id)
        ->and($report->description)->toBe('Test incident description')
        ->and($report->location)->toBe(ReportLocation::Minecraft)
        ->and($report->actions_taken)->toBe('Verbal warning given')
        ->and($report->severity)->toBe(ReportSeverity::Minor)
        ->and($report->witnesses)->toBe('PlayerA, PlayerB')
        ->and($report->status)->toBe(ReportStatus::Draft)
        ->and($report->published_at)->toBeNull()
        ->and($report->publisher_user_id)->toBeNull();
});

it('does not record activity when draft report is created', function () {
    $reporter = officerCommand();
    $subject = User::factory()->create();

    CreateDisciplineReport::run(
        $subject,
        $reporter,
        'Test incident',
        ReportLocation::DiscordText,
        'Warning issued',
        ReportSeverity::Moderate,
    );

    $this->assertDatabaseMissing('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $subject->id,
        'action' => 'discipline_report_created',
    ]);
});

it('notifies quartermaster department when non-officer creates report', function () {
    Notification::fake();

    $reporter = crewQuartermaster();
    $subject = User::factory()->create();
    $qmOfficer = officerQuartermaster();

    CreateDisciplineReport::run(
        $subject,
        $reporter,
        'Test incident',
        ReportLocation::Minecraft,
        'Warning issued',
        ReportSeverity::Minor,
    );

    Notification::assertSentTo($qmOfficer, DisciplineReportPendingReviewNotification::class);
});

it('does not notify the reporter even if they are in the quartermaster department', function () {
    Notification::fake();

    $reporter = crewQuartermaster();
    $subject = User::factory()->create();

    CreateDisciplineReport::run(
        $subject,
        $reporter,
        'Test incident',
        ReportLocation::Minecraft,
        'Warning issued',
        ReportSeverity::Minor,
    );

    Notification::assertNotSentTo($reporter, DisciplineReportPendingReviewNotification::class);
});

it('does not notify quartermaster when officer creates report', function () {
    Notification::fake();

    $reporter = officerCommand();
    $subject = User::factory()->create();
    $qmOfficer = officerQuartermaster();

    CreateDisciplineReport::run(
        $subject,
        $reporter,
        'Test incident',
        ReportLocation::Minecraft,
        'Warning issued',
        ReportSeverity::Minor,
    );

    Notification::assertNotSentTo($qmOfficer, DisciplineReportPendingReviewNotification::class);
});

it('creates report with null witnesses when not provided', function () {
    $reporter = officerCommand();
    $subject = User::factory()->create();

    $report = CreateDisciplineReport::run(
        $subject,
        $reporter,
        'Test incident',
        ReportLocation::Other,
        'Warning issued',
        ReportSeverity::Trivial,
    );

    expect($report->witnesses)->toBeNull();
});
