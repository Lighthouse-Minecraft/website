<?php

declare(strict_types=1);

use App\Actions\WithdrawApplication;
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

it('sets status to withdrawn', function () {
    $user = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $application = StaffApplication::factory()->for($user)->for($position, 'staffPosition')->create();

    WithdrawApplication::run($application, $user);

    $application->refresh();

    expect($application->status)->toBe(ApplicationStatus::Withdrawn);
});

it('records activity log', function () {
    $user = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $application = StaffApplication::factory()->for($user)->for($position, 'staffPosition')->create();

    WithdrawApplication::run($application, $user);

    expect(ActivityLog::where('subject_type', StaffApplication::class)
        ->where('subject_id', $application->id)
        ->where('action', 'application_withdrawn')
        ->exists())->toBeTrue();
});

it('only allows applicant to withdraw own application', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $application = StaffApplication::factory()->for($user)->for($position, 'staffPosition')->create();

    expect(fn () => WithdrawApplication::run($application, $otherUser))
        ->toThrow(\RuntimeException::class);
});

it('rejects withdrawal of terminal application', function () {
    $user = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $application = StaffApplication::factory()->approved()->for($user)->for($position, 'staffPosition')->create();

    expect(fn () => WithdrawApplication::run($application, $user))
        ->toThrow(\RuntimeException::class);
});
