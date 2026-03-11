<?php

declare(strict_types=1);

use App\Actions\CreateCommunityQuestion;
use App\Enums\CommunityQuestionStatus;
use App\Models\CommunityQuestion;

uses()->group('community-stories', 'actions');

it('creates a question in draft status', function () {
    $staff = loginAsAdmin();

    $question = CreateCommunityQuestion::run($staff, 'What is your favorite Minecraft memory?');

    expect($question->fresh()->status)->toBe(CommunityQuestionStatus::Draft)
        ->and($question->question_text)->toBe('What is your favorite Minecraft memory?')
        ->and($question->created_by)->toBe($staff->id);
});

it('creates a question with scheduled status and dates', function () {
    $staff = loginAsAdmin();
    $start = now()->addDays(7);
    $end = now()->addDays(14);

    $question = CreateCommunityQuestion::run(
        $staff, 'What does community mean to you?', 'Share your thoughts.', CommunityQuestionStatus::Scheduled, $start, $end
    );

    expect($question->fresh()->status)->toBe(CommunityQuestionStatus::Scheduled)
        ->and($question->start_date->toDateString())->toBe($start->toDateString())
        ->and($question->end_date->toDateString())->toBe($end->toDateString())
        ->and($question->description)->toBe('Share your thoughts.');
});

it('links suggestion when created from a suggestion', function () {
    $staff = loginAsAdmin();
    $suggestion = \App\Models\QuestionSuggestion::factory()->create();

    $question = CreateCommunityQuestion::run(
        $staff, $suggestion->question_text,
        suggestionId: $suggestion->id,
        suggestedBy: $suggestion->user_id,
    );

    expect($question->fresh()->suggestion_id)->toBe($suggestion->id)
        ->and($question->suggested_by)->toBe($suggestion->user_id);
});

it('records activity', function () {
    $staff = loginAsAdmin();

    $question = CreateCommunityQuestion::run($staff, 'Test question for the community?');

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => CommunityQuestion::class,
        'subject_id' => $question->id,
        'action' => 'community_question_created',
    ]);
});
