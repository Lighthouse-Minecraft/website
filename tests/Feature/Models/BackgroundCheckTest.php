<?php

declare(strict_types=1);

use App\Enums\BackgroundCheckStatus;
use App\Models\BackgroundCheck;
use App\Models\BackgroundCheckDocument;
use App\Models\User;

uses()->group('background-checks', 'models');

it('casts status to BackgroundCheckStatus enum', function () {
    $check = BackgroundCheck::factory()->create(['status' => BackgroundCheckStatus::Pending]);

    expect($check->fresh()->status)->toBe(BackgroundCheckStatus::Pending);
});

it('casts locked_at to datetime', function () {
    $check = BackgroundCheck::factory()->create(['locked_at' => now()]);

    expect($check->fresh()->locked_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('isLocked returns false when locked_at is null', function () {
    $check = BackgroundCheck::factory()->create(['locked_at' => null]);

    expect($check->isLocked())->toBeFalse();
});

it('isLocked returns true when locked_at is set', function () {
    $check = BackgroundCheck::factory()->create(['locked_at' => now()]);

    expect($check->isLocked())->toBeTrue();
});

it('belongs to the subject user', function () {
    $user = User::factory()->create();
    $check = BackgroundCheck::factory()->create(['user_id' => $user->id]);

    expect($check->user->id)->toBe($user->id);
});

it('belongs to the run-by user', function () {
    $manager = User::factory()->create();
    $check = BackgroundCheck::factory()->create(['run_by_user_id' => $manager->id]);

    expect($check->runByUser->id)->toBe($manager->id);
});

it('has many documents', function () {
    $check = BackgroundCheck::factory()->create();
    BackgroundCheckDocument::factory()->count(2)->create(['background_check_id' => $check->id]);

    expect($check->documents)->toHaveCount(2);
});

it('soft deletes background check', function () {
    $check = BackgroundCheck::factory()->create();
    $check->delete();

    expect(BackgroundCheck::find($check->id))->toBeNull()
        ->and(BackgroundCheck::withTrashed()->find($check->id))->not->toBeNull();
});

it('soft deletes background check document', function () {
    $doc = BackgroundCheckDocument::factory()->create();
    $doc->delete();

    expect(BackgroundCheckDocument::find($doc->id))->toBeNull()
        ->and(BackgroundCheckDocument::withTrashed()->find($doc->id))->not->toBeNull();
});

it('user has many background checks', function () {
    $user = User::factory()->create();
    BackgroundCheck::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->backgroundChecks)->toHaveCount(3);
});

it('BackgroundCheckStatus Deliberating has correct label', function () {
    expect(BackgroundCheckStatus::Deliberating->label())->toBe('Deliberating');
});

it('BackgroundCheckStatus Deliberating has a color', function () {
    expect(BackgroundCheckStatus::Deliberating->color())->toBeString()->not->toBeEmpty();
});

it('terminal statuses are Passed, Failed, and Waived', function () {
    expect(BackgroundCheckStatus::Passed->isTerminal())->toBeTrue()
        ->and(BackgroundCheckStatus::Failed->isTerminal())->toBeTrue()
        ->and(BackgroundCheckStatus::Waived->isTerminal())->toBeTrue();
});

it('non-terminal statuses are Pending and Deliberating', function () {
    expect(BackgroundCheckStatus::Pending->isTerminal())->toBeFalse()
        ->and(BackgroundCheckStatus::Deliberating->isTerminal())->toBeFalse();
});
