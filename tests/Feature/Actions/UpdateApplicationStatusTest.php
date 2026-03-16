<?php

declare(strict_types=1);

use App\Actions\UpdateApplicationStatus;
use App\Enums\ApplicationStatus;
use App\Enums\BackgroundCheckStatus;
use App\Models\StaffApplication;
use App\Models\StaffPosition;
use App\Models\User;

uses()->group('applications', 'actions');

it('updates status and reviewer', function () {
    $user = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $application = StaffApplication::factory()->for($user)->for($position, 'staffPosition')->create(['status' => ApplicationStatus::Submitted]);
    $reviewer = loginAsAdmin();

    UpdateApplicationStatus::run($application, ApplicationStatus::UnderReview, $reviewer);

    $fresh = $application->fresh();
    expect($fresh->status)->toBe(ApplicationStatus::UnderReview)
        ->and($fresh->reviewed_by)->toBe($reviewer->id);
});

it('appends reviewer notes with timestamp', function () {
    $user = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $application = StaffApplication::factory()->for($user)->for($position, 'staffPosition')->create(['status' => ApplicationStatus::Submitted]);
    $reviewer = loginAsAdmin();

    UpdateApplicationStatus::run($application, ApplicationStatus::UnderReview, $reviewer, 'Looks good so far');

    $fresh = $application->fresh();
    expect($fresh->reviewer_notes)->toContain('Looks good so far')
        ->and($fresh->reviewer_notes)->toContain($reviewer->name);
});

it('records activity log', function () {
    $user = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $application = StaffApplication::factory()->for($user)->for($position, 'staffPosition')->create(['status' => ApplicationStatus::Submitted]);
    $reviewer = loginAsAdmin();

    UpdateApplicationStatus::run($application, ApplicationStatus::UnderReview, $reviewer);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => StaffApplication::class,
        'subject_id' => $application->id,
        'action' => 'application_status_changed',
    ]);
});

it('sets background check status when moving to background check', function () {
    $user = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $application = StaffApplication::factory()->for($user)->for($position, 'staffPosition')->create(['status' => ApplicationStatus::Submitted]);
    $reviewer = loginAsAdmin();

    UpdateApplicationStatus::run($application, ApplicationStatus::BackgroundCheck, $reviewer);

    expect($application->fresh()->background_check_status)->toBe(BackgroundCheckStatus::Pending);
});

it('creates interview discussion when status moves to interview', function () {
    $user = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $application = StaffApplication::factory()->for($user)->for($position, 'staffPosition')->create(['status' => ApplicationStatus::Submitted]);
    $reviewer = loginAsAdmin();

    UpdateApplicationStatus::run($application, ApplicationStatus::Interview, $reviewer);

    expect($application->fresh()->interview_thread_id)->not->toBeNull();
});

it('adds applicant as participant in interview discussion', function () {
    $user = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $application = StaffApplication::factory()->for($user)->for($position, 'staffPosition')->create(['status' => ApplicationStatus::Submitted]);
    $reviewer = loginAsAdmin();

    UpdateApplicationStatus::run($application, ApplicationStatus::Interview, $reviewer);

    $thread = $application->fresh()->interviewThread;
    expect($thread)->not->toBeNull()
        ->and($thread->participants->pluck('id'))->toContain($user->id);
});
