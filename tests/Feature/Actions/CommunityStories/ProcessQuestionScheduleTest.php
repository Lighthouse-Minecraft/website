<?php

declare(strict_types=1);

use App\Actions\ProcessQuestionSchedule;
use App\Enums\CommunityQuestionStatus;
use App\Models\CommunityQuestion;

uses()->group('community-stories', 'actions');

it('activates a scheduled question whose start_date has passed', function () {
    loginAsAdmin();
    $question = CommunityQuestion::factory()->create([
        'status' => CommunityQuestionStatus::Scheduled,
        'start_date' => now()->subHour(),
        'end_date' => now()->addDays(7),
    ]);

    $result = ProcessQuestionSchedule::run();

    expect($question->fresh()->status)->toBe(CommunityQuestionStatus::Active)
        ->and($result['activated'])->toBe(1);
});

it('archives an active question whose end_date has passed', function () {
    loginAsAdmin();
    $question = CommunityQuestion::factory()->create([
        'status' => CommunityQuestionStatus::Active,
        'start_date' => now()->subDays(7),
        'end_date' => now()->subHour(),
    ]);

    $result = ProcessQuestionSchedule::run();

    expect($question->fresh()->status)->toBe(CommunityQuestionStatus::Archived)
        ->and($result['archived'])->toBe(1);
});

it('archives old active question when new one activates', function () {
    loginAsAdmin();
    $oldActive = CommunityQuestion::factory()->create([
        'status' => CommunityQuestionStatus::Active,
        'start_date' => now()->subDays(14),
        'end_date' => null,
    ]);

    $newScheduled = CommunityQuestion::factory()->create([
        'status' => CommunityQuestionStatus::Scheduled,
        'start_date' => now()->subHour(),
        'end_date' => now()->addDays(7),
    ]);

    ProcessQuestionSchedule::run();

    expect($oldActive->fresh()->status)->toBe(CommunityQuestionStatus::Archived)
        ->and($newScheduled->fresh()->status)->toBe(CommunityQuestionStatus::Active);
});

it('does not change draft questions', function () {
    loginAsAdmin();
    $question = CommunityQuestion::factory()->create([
        'status' => CommunityQuestionStatus::Draft,
        'start_date' => now()->subHour(),
    ]);

    ProcessQuestionSchedule::run();

    expect($question->fresh()->status)->toBe(CommunityQuestionStatus::Draft);
});

it('does not activate a question whose start_date is in the future', function () {
    loginAsAdmin();
    $question = CommunityQuestion::factory()->scheduled()->create();

    ProcessQuestionSchedule::run();

    expect($question->fresh()->status)->toBe(CommunityQuestionStatus::Scheduled);
});
