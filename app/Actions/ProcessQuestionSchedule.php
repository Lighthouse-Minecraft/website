<?php

namespace App\Actions;

use App\Enums\CommunityQuestionStatus;
use App\Models\CommunityQuestion;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessQuestionSchedule
{
    use AsAction;

    public function handle(): array
    {
        $activated = 0;
        $archived = 0;

        // Archive active questions whose end_date has passed
        $expiredActive = CommunityQuestion::active()
            ->whereNotNull('end_date')
            ->where('end_date', '<=', now())
            ->get();

        foreach ($expiredActive as $question) {
            $question->update(['status' => CommunityQuestionStatus::Archived]);
            RecordActivity::run($question, 'community_question_archived', "Question #{$question->id} auto-archived (end date passed).");
            $archived++;
        }

        // Activate scheduled questions whose start_date has passed
        $readyToActivate = CommunityQuestion::scheduled()
            ->where('start_date', '<=', now())
            ->get();

        foreach ($readyToActivate as $question) {
            // Archive any currently active question first
            $currentActive = CommunityQuestion::active()->where('id', '!=', $question->id)->get();
            foreach ($currentActive as $activeQuestion) {
                $activeQuestion->update(['status' => CommunityQuestionStatus::Archived]);
                RecordActivity::run($activeQuestion, 'community_question_archived', "Question #{$activeQuestion->id} auto-archived (replaced by question #{$question->id}).");
                $archived++;
            }

            $question->update(['status' => CommunityQuestionStatus::Active]);
            RecordActivity::run($question, 'community_question_activated', "Question #{$question->id} auto-activated (start date reached).");
            $activated++;
        }

        return ['activated' => $activated, 'archived' => $archived];
    }
}
