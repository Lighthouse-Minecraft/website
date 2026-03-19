<?php

declare(strict_types=1);

use App\Actions\ApproveApplication;
use App\Enums\ApplicationStatus;
use App\Enums\BackgroundCheckStatus;
use App\Models\ActivityLog;
use App\Models\StaffApplication;
use App\Models\StaffPosition;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

uses()->group('applications', 'actions');

beforeEach(function () {
    Notification::fake();
});

it('sets status to approved with background check and conditions', function () {
    $reviewer = User::factory()->create();
    $user = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $application = StaffApplication::factory()->backgroundCheck()->for($user)->for($position, 'staffPosition')->create();

    ApproveApplication::run($application, $reviewer, BackgroundCheckStatus::Passed, 'Trial period', 'Good candidate');

    $application->refresh();

    expect($application->status)->toBe(ApplicationStatus::Approved)
        ->and($application->background_check_status)->toBe(BackgroundCheckStatus::Passed)
        ->and($application->conditions)->toBe('Trial period')
        ->and($application->reviewed_by)->toBe($reviewer->id)
        ->and($application->reviewer_notes)->toContain('Good candidate');
});

it('assigns the applicant to the staff position with synced fields', function () {
    $reviewer = User::factory()->create();
    $user = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $application = StaffApplication::factory()->backgroundCheck()->for($user)->for($position, 'staffPosition')->create();

    ApproveApplication::run($application, $reviewer, BackgroundCheckStatus::Passed);

    $position->refresh();
    $user->refresh();

    expect($position->user_id)->toBe($user->id)
        ->and($user->staff_title)->toBe($position->title)
        ->and($user->staff_department)->toBe($position->department)
        ->and($user->staff_rank)->toBe($position->rank);
});

it('records activity log', function () {
    $reviewer = User::factory()->create();
    $user = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $application = StaffApplication::factory()->backgroundCheck()->for($user)->for($position, 'staffPosition')->create();

    ApproveApplication::run($application, $reviewer, BackgroundCheckStatus::Passed);

    expect(ActivityLog::where('subject_type', StaffApplication::class)
        ->where('subject_id', $application->id)
        ->where('action', 'application_approved')
        ->exists())->toBeTrue();
});
