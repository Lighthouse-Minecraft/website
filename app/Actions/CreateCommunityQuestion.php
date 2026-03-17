<?php

namespace App\Actions;

use App\Enums\CommunityQuestionStatus;
use App\Models\CommunityQuestion;
use App\Models\User;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateCommunityQuestion
{
    use AsAction;

    public function handle(
        User $staff,
        string $questionText,
        ?string $description = null,
        CommunityQuestionStatus $status = CommunityQuestionStatus::Draft,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?int $suggestionId = null,
        ?int $suggestedBy = null,
    ): CommunityQuestion {
        // If creating as active, archive any other active questions first
        if ($status === CommunityQuestionStatus::Active) {
            CommunityQuestion::where('status', CommunityQuestionStatus::Active)
                ->update(['status' => CommunityQuestionStatus::Archived]);
        }

        $question = CommunityQuestion::create([
            'question_text' => $questionText,
            'description' => $description,
            'status' => $status,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'created_by' => $staff->id,
            'suggestion_id' => $suggestionId,
            'suggested_by' => $suggestedBy,
        ]);

        RecordActivity::run($question, 'community_question_created', "Community question created by {$staff->name}.");

        return $question;
    }
}
