<?php

namespace App\Actions;

use App\Enums\CommunityQuestionStatus;
use App\Models\CommunityQuestion;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateCommunityQuestion
{
    use AsAction;

    public function handle(CommunityQuestion $question, User $staff, array $data): CommunityQuestion
    {
        // If setting this question to active, archive any other active questions first
        if (isset($data['status']) && $data['status'] === CommunityQuestionStatus::Active) {
            CommunityQuestion::where('status', CommunityQuestionStatus::Active)
                ->where('id', '!=', $question->id)
                ->update(['status' => CommunityQuestionStatus::Archived]);
        }

        $question->update($data);

        RecordActivity::run(
            $question,
            'community_question_updated',
            "Community question updated by {$staff->name}. Status: {$question->status->value}."
        );

        return $question->fresh();
    }
}
