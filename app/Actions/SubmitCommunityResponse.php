<?php

namespace App\Actions;

use App\Enums\CommunityQuestionStatus;
use App\Enums\CommunityResponseStatus;
use App\Enums\MembershipLevel;
use App\Models\CommunityQuestion;
use App\Models\CommunityResponse;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Lorisleiva\Actions\Concerns\AsAction;

class SubmitCommunityResponse
{
    use AsAction;

    public function handle(CommunityQuestion $question, User $user, string $body, ?UploadedFile $image = null): CommunityResponse
    {
        // Prevent duplicate responses
        if (CommunityResponse::where('community_question_id', $question->id)->where('user_id', $user->id)->exists()) {
            throw new \RuntimeException('You have already responded to this question.');
        }

        // Rank-based access check
        if ($question->isActive()) {
            if (! $user->isAtLeastLevel(MembershipLevel::Traveler)) {
                throw new \RuntimeException('You must be at least a Traveler to respond.');
            }
        } elseif ($question->isArchived()) {
            if (! $user->isAtLeastLevel(MembershipLevel::Resident)) {
                throw new \RuntimeException('You must be at least a Resident to respond to past questions.');
            }

            // Must have answered the current active question first
            $activeQuestion = CommunityQuestion::active()->first();
            if ($activeQuestion) {
                $hasAnsweredActive = CommunityResponse::where('community_question_id', $activeQuestion->id)
                    ->where('user_id', $user->id)
                    ->exists();

                if (! $hasAnsweredActive) {
                    throw new \RuntimeException('You must answer the current question before responding to a past question.');
                }

                // Per-cycle limit: only one archived question response per active question cycle
                $archivedResponseCount = CommunityResponse::where('user_id', $user->id)
                    ->where('community_question_id', '!=', $activeQuestion->id)
                    ->whereHas('question', fn ($q) => $q->archived())
                    ->where('created_at', '>=', $activeQuestion->start_date)
                    ->count();

                if ($archivedResponseCount >= 1) {
                    throw new \RuntimeException('You may only respond to one past question per cycle.');
                }
            }
        } else {
            throw new \RuntimeException('This question is not accepting responses.');
        }

        // Store image if provided
        $imagePath = null;
        if ($image) {
            $imagePath = $image->store('community-stories', config('filesystems.public'));
        }

        $response = CommunityResponse::create([
            'community_question_id' => $question->id,
            'user_id' => $user->id,
            'body' => $body,
            'image_path' => $imagePath,
            'status' => CommunityResponseStatus::Submitted,
        ]);

        RecordActivity::run($response, 'community_response_submitted', "Response submitted by {$user->name} to question #{$question->id}.");

        return $response;
    }
}
