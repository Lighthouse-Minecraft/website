<?php

declare(strict_types=1);

use App\Actions\AddApplicationNote;
use App\Models\ActivityLog;
use App\Models\StaffApplication;
use App\Models\StaffPosition;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

uses()->group('applications', 'actions');

beforeEach(function () {
    Notification::fake();
});

it('creates a note on the application', function () {
    $staff = User::factory()->create();
    $user = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $application = StaffApplication::factory()->for($user)->for($position, 'staffPosition')->create();

    AddApplicationNote::run($application, $staff, 'Test note');

    expect($application->notes()->count())->toBe(1)
        ->and($application->notes()->first()->body)->toBe('Test note')
        ->and($application->notes()->first()->user_id)->toBe($staff->id);
});

it('records activity log', function () {
    $staff = User::factory()->create();
    $user = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $application = StaffApplication::factory()->for($user)->for($position, 'staffPosition')->create();

    AddApplicationNote::run($application, $staff, 'Test note');

    expect(ActivityLog::where('subject_type', StaffApplication::class)
        ->where('subject_id', $application->id)
        ->where('action', 'application_note_added')
        ->exists())->toBeTrue();
});
