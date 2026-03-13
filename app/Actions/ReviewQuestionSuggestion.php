<?php

namespace App\Actions;

use App\Enums\CommunityQuestionStatus;
use App\Enums\QuestionSuggestionStatus;
use App\Models\QuestionSuggestion;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class ReviewQuestionSuggestion
{
    use AsAction;

    public function handle(QuestionSuggestion $suggestion, User $staff, QuestionSuggestionStatus $outcome): QuestionSuggestion
    {
        $suggestion->update([
            'status' => $outcome,
            'reviewed_by' => $staff->id,
            'reviewed_at' => now(),
        ]);

        RecordActivity::run(
            $suggestion,
            'question_suggestion_reviewed',
            "Suggestion #{$suggestion->id} {$outcome->value} by {$staff->name}."
        );

        // Auto-create draft question when approved
        if ($outcome === QuestionSuggestionStatus::Approved) {
            CreateCommunityQuestion::run(
                staff: $staff,
                questionText: $suggestion->question_text,
                status: CommunityQuestionStatus::Draft,
                suggestionId: $suggestion->id,
                suggestedBy: $suggestion->user_id,
            );
        }

        return $suggestion->fresh();
    }
}
