<?php

declare(strict_types=1);

use App\Actions\AddBackgroundCheckNote;
use App\Actions\CreateBackgroundCheck;
use App\Actions\UpdateBackgroundCheckStatus;
use App\Enums\BackgroundCheckStatus;
use App\Models\BackgroundCheck;
use App\Models\User;
use Carbon\Carbon;

uses()->group('background-checks', 'actions');

// === CreateBackgroundCheck ===

it('creates a background check with Pending status', function () {
    $user = User::factory()->create();
    $runBy = User::factory()->create();

    $check = CreateBackgroundCheck::run($user, $runBy, 'Checkr', Carbon::yesterday());

    expect($check->fresh()->status)->toBe(BackgroundCheckStatus::Pending)
        ->and($check->fresh()->user_id)->toBe($user->id)
        ->and($check->fresh()->run_by_user_id)->toBe($runBy->id)
        ->and($check->fresh()->service)->toBe('Checkr');
});

it('creates a background check with optional notes', function () {
    $user = User::factory()->create();
    $runBy = User::factory()->create();

    $check = CreateBackgroundCheck::run($user, $runBy, 'Checkr', Carbon::yesterday(), 'Initial notes.');

    expect($check->fresh()->notes)->toBe('Initial notes.');
});

it('creates a background check without notes when omitted', function () {
    $user = User::factory()->create();
    $runBy = User::factory()->create();

    $check = CreateBackgroundCheck::run($user, $runBy, 'Checkr', Carbon::yesterday());

    expect($check->fresh()->notes)->toBeNull();
});

it('rejects a future completed_date', function () {
    $user = User::factory()->create();
    $runBy = User::factory()->create();

    expect(fn () => CreateBackgroundCheck::run($user, $runBy, 'Checkr', Carbon::tomorrow()))
        ->toThrow(\InvalidArgumentException::class, 'future');
});

it('accepts today as a valid completed_date', function () {
    $user = User::factory()->create();
    $runBy = User::factory()->create();

    $check = CreateBackgroundCheck::run($user, $runBy, 'Checkr', Carbon::today());

    expect($check->fresh()->status)->toBe(BackgroundCheckStatus::Pending);
});

it('writes activity log on creation', function () {
    $user = User::factory()->create();
    $runBy = User::factory()->create();

    $check = CreateBackgroundCheck::run($user, $runBy, 'Checkr', Carbon::yesterday());

    expect(\App\Models\ActivityLog::where('subject_type', BackgroundCheck::class)
        ->where('subject_id', $check->id)
        ->where('action', 'background_check_created')
        ->exists())->toBeTrue();
});

// === UpdateBackgroundCheckStatus ===

it('transitions a Pending check to Deliberating', function () {
    $user = User::factory()->create();
    $check = BackgroundCheck::factory()->create(['status' => BackgroundCheckStatus::Pending]);

    UpdateBackgroundCheckStatus::run($check, BackgroundCheckStatus::Deliberating, $user);

    expect($check->fresh()->status)->toBe(BackgroundCheckStatus::Deliberating)
        ->and($check->fresh()->locked_at)->toBeNull();
});

it('transitions a Pending check to Passed and sets locked_at', function () {
    $user = User::factory()->create();
    $check = BackgroundCheck::factory()->create(['status' => BackgroundCheckStatus::Pending]);

    UpdateBackgroundCheckStatus::run($check, BackgroundCheckStatus::Passed, $user);

    expect($check->fresh()->status)->toBe(BackgroundCheckStatus::Passed)
        ->and($check->fresh()->locked_at)->not->toBeNull();
});

it('transitions a Pending check to Failed and sets locked_at', function () {
    $user = User::factory()->create();
    $check = BackgroundCheck::factory()->create(['status' => BackgroundCheckStatus::Pending]);

    UpdateBackgroundCheckStatus::run($check, BackgroundCheckStatus::Failed, $user);

    expect($check->fresh()->status)->toBe(BackgroundCheckStatus::Failed)
        ->and($check->fresh()->locked_at)->not->toBeNull();
});

it('transitions a Pending check to Waived and sets locked_at', function () {
    $user = User::factory()->create();
    $check = BackgroundCheck::factory()->create(['status' => BackgroundCheckStatus::Pending]);

    UpdateBackgroundCheckStatus::run($check, BackgroundCheckStatus::Waived, $user);

    expect($check->fresh()->status)->toBe(BackgroundCheckStatus::Waived)
        ->and($check->fresh()->locked_at)->not->toBeNull();
});

it('transitions a Deliberating check to Passed', function () {
    $user = User::factory()->create();
    $check = BackgroundCheck::factory()->deliberating()->create();

    UpdateBackgroundCheckStatus::run($check, BackgroundCheckStatus::Passed, $user);

    expect($check->fresh()->status)->toBe(BackgroundCheckStatus::Passed);
});

it('throws when attempting to change a Passed (locked) check', function () {
    $user = User::factory()->create();
    $check = BackgroundCheck::factory()->passed()->create();

    expect(fn () => UpdateBackgroundCheckStatus::run($check, BackgroundCheckStatus::Pending, $user))
        ->toThrow(\InvalidArgumentException::class);
});

it('throws when attempting to change a Failed (locked) check', function () {
    $user = User::factory()->create();
    $check = BackgroundCheck::factory()->failed()->create();

    expect(fn () => UpdateBackgroundCheckStatus::run($check, BackgroundCheckStatus::Pending, $user))
        ->toThrow(\InvalidArgumentException::class);
});

it('throws when attempting to change a Waived (locked) check', function () {
    $user = User::factory()->create();
    $check = BackgroundCheck::factory()->waived()->create();

    expect(fn () => UpdateBackgroundCheckStatus::run($check, BackgroundCheckStatus::Pending, $user))
        ->toThrow(\InvalidArgumentException::class);
});

it('writes activity log on status update', function () {
    $user = User::factory()->create();
    $check = BackgroundCheck::factory()->create(['status' => BackgroundCheckStatus::Pending]);

    UpdateBackgroundCheckStatus::run($check, BackgroundCheckStatus::Passed, $user);

    expect(\App\Models\ActivityLog::where('subject_type', BackgroundCheck::class)
        ->where('subject_id', $check->id)
        ->where('action', 'background_check_status_updated')
        ->exists())->toBeTrue();
});

// === AddBackgroundCheckNote ===

it('appends a note to a check with no existing notes', function () {
    $author = User::factory()->create(['name' => 'Jane Doe']);
    $check = BackgroundCheck::factory()->create(['notes' => null]);

    AddBackgroundCheckNote::run($check, 'Looks good.', $author);

    expect($check->fresh()->notes)->toContain('Jane Doe: Looks good.');
});

it('appends a note in [YYYY-MM-DD HH:mm] format', function () {
    $author = User::factory()->create(['name' => 'Jane Doe']);
    $check = BackgroundCheck::factory()->create(['notes' => null]);

    AddBackgroundCheckNote::run($check, 'Looks good.', $author);

    expect($check->fresh()->notes)->toMatch('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}\]/');
});

it('appends a note to a check with existing notes', function () {
    $author = User::factory()->create(['name' => 'Jane Doe']);
    $check = BackgroundCheck::factory()->create(['notes' => 'Existing note.']);

    AddBackgroundCheckNote::run($check, 'Second note.', $author);

    expect($check->fresh()->notes)->toContain('Existing note.')
        ->and($check->fresh()->notes)->toContain('Jane Doe: Second note.');
});

it('appends a note to a locked (terminal status) check', function () {
    $author = User::factory()->create();
    $check = BackgroundCheck::factory()->passed()->create(['notes' => null]);

    AddBackgroundCheckNote::run($check, 'Post-lock note.', $author);

    expect($check->fresh()->notes)->toContain('Post-lock note.');
});

it('writes activity log on note addition', function () {
    $author = User::factory()->create();
    $check = BackgroundCheck::factory()->create();

    AddBackgroundCheckNote::run($check, 'A note.', $author);

    expect(\App\Models\ActivityLog::where('subject_type', BackgroundCheck::class)
        ->where('subject_id', $check->id)
        ->where('action', 'background_check_note_added')
        ->exists())->toBeTrue();
});
