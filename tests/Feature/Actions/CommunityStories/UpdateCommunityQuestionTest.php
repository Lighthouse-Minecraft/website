<?php

declare(strict_types=1);

use App\Actions\UpdateCommunityQuestion;
use App\Enums\CommunityQuestionStatus;
use App\Models\CommunityQuestion;

uses()->group('community-stories', 'actions');

it('archives existing active question when setting another to active', function () {
    $staff = loginAsAdmin();

    $existing = CommunityQuestion::factory()->create(['status' => CommunityQuestionStatus::Active]);
    $draft = CommunityQuestion::factory()->create(['status' => CommunityQuestionStatus::Draft]);

    UpdateCommunityQuestion::run($draft, $staff, ['status' => CommunityQuestionStatus::Active]);

    expect($existing->fresh()->status)->toBe(CommunityQuestionStatus::Archived)
        ->and($draft->fresh()->status)->toBe(CommunityQuestionStatus::Active);
});

it('does not archive other questions when updating without changing to active', function () {
    $staff = loginAsAdmin();

    $active = CommunityQuestion::factory()->create(['status' => CommunityQuestionStatus::Active]);
    $draft = CommunityQuestion::factory()->create(['status' => CommunityQuestionStatus::Draft]);

    UpdateCommunityQuestion::run($draft, $staff, ['question_text' => 'Updated text here for testing']);

    expect($active->fresh()->status)->toBe(CommunityQuestionStatus::Active)
        ->and($draft->fresh()->status)->toBe(CommunityQuestionStatus::Draft);
});

it('does not archive itself when re-saving an already active question', function () {
    $staff = loginAsAdmin();

    $active = CommunityQuestion::factory()->create(['status' => CommunityQuestionStatus::Active]);

    UpdateCommunityQuestion::run($active, $staff, [
        'status' => CommunityQuestionStatus::Active,
        'question_text' => 'Updated active question text here',
    ]);

    expect($active->fresh()->status)->toBe(CommunityQuestionStatus::Active);
});
