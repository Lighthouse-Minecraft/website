<?php

declare(strict_types=1);

use App\Actions\DenyApplication;
use App\Enums\ApplicationStatus;
use App\Models\ActivityLog;
use App\Models\StaffApplication;
use App\Models\StaffPosition;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

uses()->group('applications', 'actions');

beforeEach(function () {
    Notification::fake();
});

it('sets status to denied with reviewer notes', function () {
    $reviewer = User::factory()->create();
    $user = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $application = StaffApplication::factory()->for($user)->for($position, 'staffPosition')->create();

    DenyApplication::run($application, $reviewer, 'Does not meet requirements');

    $application->refresh();

    expect($application->status)->toBe(ApplicationStatus::Denied)
        ->and($application->reviewed_by)->toBe($reviewer->id)
        ->and($application->reviewer_notes)->toContain('Does not meet requirements');
});

it('records activity log', function () {
    $reviewer = User::factory()->create();
    $user = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $application = StaffApplication::factory()->for($user)->for($position, 'staffPosition')->create();

    DenyApplication::run($application, $reviewer, 'Not a good fit');

    expect(ActivityLog::where('subject_type', StaffApplication::class)
        ->where('subject_id', $application->id)
        ->where('action', 'application_denied')
        ->exists())->toBeTrue();
});
