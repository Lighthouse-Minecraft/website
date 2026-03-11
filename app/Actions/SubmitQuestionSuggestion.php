<?php

namespace App\Actions;

use App\Enums\QuestionSuggestionStatus;
use App\Models\QuestionSuggestion;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class SubmitQuestionSuggestion
{
    use AsAction;

    public function handle(User $user, string $questionText): QuestionSuggestion
    {
        $suggestion = QuestionSuggestion::create([
            'user_id' => $user->id,
            'question_text' => $questionText,
            'status' => QuestionSuggestionStatus::Suggested,
        ]);

        RecordActivity::run($suggestion, 'question_suggestion_submitted', "Question suggested by {$user->name}.");

        return $suggestion;
    }
}
