<?php

declare(strict_types=1);

use App\Actions\SubmitQuestionSuggestion;
use App\Enums\QuestionSuggestionStatus;
use App\Models\QuestionSuggestion;
use App\Models\User;

uses()->group('community-stories', 'actions');

it('creates a suggestion with suggested status', function () {
    $user = loginAs(membershipCitizen());

    $suggestion = SubmitQuestionSuggestion::run($user, 'What is your favorite Lighthouse build?');

    expect($suggestion->fresh()->status)->toBe(QuestionSuggestionStatus::Suggested)
        ->and($suggestion->question_text)->toBe('What is your favorite Lighthouse build?')
        ->and($suggestion->user_id)->toBe($user->id);
});

it('records activity', function () {
    $user = loginAs(membershipCitizen());

    $suggestion = SubmitQuestionSuggestion::run($user, 'What is your favorite Lighthouse build?');

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => QuestionSuggestion::class,
        'subject_id' => $suggestion->id,
        'action' => 'question_suggestion_submitted',
    ]);
});
