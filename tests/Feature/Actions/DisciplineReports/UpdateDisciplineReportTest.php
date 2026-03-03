<?php

declare(strict_types=1);

use App\Actions\UpdateDisciplineReport;
use App\Enums\ReportLocation;
use App\Enums\ReportSeverity;
use App\Models\DisciplineReport;
use App\Models\User;

uses()->group('discipline-reports', 'actions');

it('updates a draft discipline report', function () {
    $reporter = officerCommand();
    $report = DisciplineReport::factory()->byReporter($reporter)->create();

    $updated = UpdateDisciplineReport::run(
        $report,
        $reporter,
        'Updated description',
        ReportLocation::DiscordVoice,
        'Updated actions taken',
        ReportSeverity::Major,
        'New witnesses',
    );

    expect($updated->description)->toBe('Updated description')
        ->and($updated->location)->toBe(ReportLocation::DiscordVoice)
        ->and($updated->actions_taken)->toBe('Updated actions taken')
        ->and($updated->severity)->toBe(ReportSeverity::Major)
        ->and($updated->witnesses)->toBe('New witnesses');
});

it('records activity when report is updated', function () {
    $editor = officerCommand();
    $subject = User::factory()->create();
    $report = DisciplineReport::factory()->forSubject($subject)->create();

    UpdateDisciplineReport::run(
        $report,
        $editor,
        'Updated description',
        ReportLocation::Minecraft,
        'Updated actions',
        ReportSeverity::Minor,
    );

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $subject->id,
        'action' => 'discipline_report_updated',
    ]);
});
