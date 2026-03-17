<?php

namespace App\Actions;

use App\Enums\CommunityQuestionStatus;
use App\Models\CommunityQuestion;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessQuestionSchedule
{
    use AsAction;

    public function handle(): array
    {
        $activated = 0;
        $archived = 0;

        return DB::transaction(function () use (&$activated, &$archived) {
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

            // Pick the single best draft question with a start_date to activate (deterministic: earliest start, lowest ID)
            $questionToActivate = CommunityQuestion::pendingActivation()
                ->where('start_date', '<=', now())
                ->orderBy('start_date', 'asc')
                ->orderBy('id', 'asc')
                ->first();

            if ($questionToActivate) {
                // Archive any currently active questions before activating the new one
                $currentActive = CommunityQuestion::active()->get();
                foreach ($currentActive as $activeQuestion) {
                    $activeQuestion->update(['status' => CommunityQuestionStatus::Archived]);
                    RecordActivity::run($activeQuestion, 'community_question_archived', "Question #{$activeQuestion->id} auto-archived (replaced by question #{$questionToActivate->id}).");
                    $archived++;
                }

                $questionToActivate->update(['status' => CommunityQuestionStatus::Active]);
                RecordActivity::run($questionToActivate, 'community_question_activated', "Question #{$questionToActivate->id} auto-activated (start date reached).");
                $activated++;

                // Archive all other overdue draft questions so they don't activate on future runs
                $staleDrafts = CommunityQuestion::pendingActivation()
                    ->where('start_date', '<=', now())
                    ->where('id', '!=', $questionToActivate->id)
                    ->get();

                foreach ($staleDrafts as $stale) {
                    $stale->update(['status' => CommunityQuestionStatus::Archived]);
                    RecordActivity::run($stale, 'community_question_archived', "Question #{$stale->id} auto-archived (stale draft question).");
                    $archived++;
                }
            }

            return ['activated' => $activated, 'archived' => $archived];
        });
    }
}
