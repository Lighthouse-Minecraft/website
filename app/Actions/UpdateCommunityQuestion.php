<?php

namespace App\Actions;

use App\Models\CommunityQuestion;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateCommunityQuestion
{
    use AsAction;

    public function handle(CommunityQuestion $question, User $staff, array $data): CommunityQuestion
    {
        $question->update($data);

        RecordActivity::run(
            $question,
            'community_question_updated',
            "Community question updated by {$staff->name}. Status: {$question->status->value}."
        );

        return $question->fresh();
    }
}
