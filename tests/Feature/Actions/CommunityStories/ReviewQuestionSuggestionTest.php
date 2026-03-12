<?php

declare(strict_types=1);

use App\Actions\ReviewQuestionSuggestion;
use App\Enums\CommunityQuestionStatus;
use App\Enums\QuestionSuggestionStatus;
use App\Models\CommunityQuestion;
use App\Models\QuestionSuggestion;

uses()->group('community-stories', 'actions');

it('approves a suggestion and auto-creates a draft question', function () {
    $staff = loginAsAdmin();
    $suggestion = QuestionSuggestion::factory()->create();

    ReviewQuestionSuggestion::run($suggestion, $staff, QuestionSuggestionStatus::Approved);

    expect($suggestion->fresh()->status)->toBe(QuestionSuggestionStatus::Approved)
        ->and($suggestion->fresh()->reviewed_by)->toBe($staff->id)
        ->and($suggestion->fresh()->reviewed_at)->not->toBeNull();

    $this->assertDatabaseHas('community_questions', [
        'question_text' => $suggestion->question_text,
        'status' => CommunityQuestionStatus::Draft->value,
        'suggestion_id' => $suggestion->id,
        'suggested_by' => $suggestion->user_id,
    ]);
});

it('rejects a suggestion without creating a question', function () {
    $staff = loginAsAdmin();
    $suggestion = QuestionSuggestion::factory()->create();

    ReviewQuestionSuggestion::run($suggestion, $staff, QuestionSuggestionStatus::Rejected);

    expect($suggestion->fresh()->status)->toBe(QuestionSuggestionStatus::Rejected);
    expect(CommunityQuestion::where('suggestion_id', $suggestion->id)->exists())->toBeFalse();
});

it('records activity', function () {
    $staff = loginAsAdmin();
    $suggestion = QuestionSuggestion::factory()->create();

    ReviewQuestionSuggestion::run($suggestion, $staff, QuestionSuggestionStatus::Approved);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => QuestionSuggestion::class,
        'subject_id' => $suggestion->id,
        'action' => 'question_suggestion_reviewed',
    ]);
});
