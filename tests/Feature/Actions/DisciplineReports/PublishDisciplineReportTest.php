<?php

declare(strict_types=1);

use App\Actions\PublishDisciplineReport;
use App\Enums\ReportStatus;
use App\Models\DisciplineReport;
use App\Models\User;
use App\Notifications\DisciplineReportPublishedNotification;
use App\Notifications\DisciplineReportPublishedParentNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

uses()->group('discipline-reports', 'actions');

it('publishes a draft report', function () {
    $publisher = officerCommand();
    $report = DisciplineReport::factory()->create();

    $published = PublishDisciplineReport::run($report, $publisher);

    expect($published->status)->toBe(ReportStatus::Published)
        ->and($published->isPublished())->toBeTrue();
});

it('sets publisher and published_at on publish', function () {
    $publisher = officerCommand();
    $report = DisciplineReport::factory()->create();

    $published = PublishDisciplineReport::run($report, $publisher);

    expect($published->publisher_user_id)->toBe($publisher->id)
        ->and($published->published_at)->not->toBeNull();
});

it('records activity when report is published', function () {
    $publisher = officerCommand();
    $subject = User::factory()->create();
    $report = DisciplineReport::factory()->forSubject($subject)->create();

    PublishDisciplineReport::run($report, $publisher);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $subject->id,
        'action' => 'discipline_report_published',
    ]);
});

it('notifies subject user when report is published', function () {
    Notification::fake();

    $publisher = officerCommand();
    $subject = User::factory()->create();
    $report = DisciplineReport::factory()->forSubject($subject)->create();

    PublishDisciplineReport::run($report, $publisher);

    Notification::assertSentTo($subject, DisciplineReportPublishedNotification::class);
});

it('sends parent-specific notification to parent accounts', function () {
    Notification::fake();

    $publisher = officerCommand();
    $parent = User::factory()->create();
    $child = User::factory()->create();
    $parent->children()->attach($child);

    $report = DisciplineReport::factory()->forSubject($child)->create();

    PublishDisciplineReport::run($report, $publisher);

    Notification::assertSentTo($parent, DisciplineReportPublishedParentNotification::class);
    Notification::assertNotSentTo($parent, DisciplineReportPublishedNotification::class);
});

it('clears the subject risk score cache when report is published', function () {
    $publisher = officerCommand();
    $subject = User::factory()->create();
    $report = DisciplineReport::factory()->forSubject($subject)->create();

    // Prime the cache
    $subject->disciplineRiskScore();
    expect(Cache::has("user.{$subject->id}.discipline_risk_score"))->toBeTrue();

    PublishDisciplineReport::run($report, $publisher);

    expect(Cache::has("user.{$subject->id}.discipline_risk_score"))->toBeFalse();
});
