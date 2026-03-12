<?php

namespace App\Actions;

use App\Enums\CommunityResponseStatus;
use App\Enums\MembershipLevel;
use App\Models\CommunityQuestion;
use App\Models\CommunityResponse;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
            if (! $activeQuestion) {
                throw new \RuntimeException('Cannot respond to past questions when no active cycle exists.');
            }

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
        } else {
            throw new \RuntimeException('This question is not accepting responses.');
        }

        // Insert the DB record first (within a transaction), then store the image
        $response = DB::transaction(function () use ($question, $user, $body) {
            return CommunityResponse::create([
                'community_question_id' => $question->id,
                'user_id' => $user->id,
                'body' => $body,
                'status' => CommunityResponseStatus::Submitted,
            ]);
        });

        // Store image after successful DB insert — if upload fails, clean up
        if ($image) {
            $imagePath = $image->store('community-stories', config('filesystems.public_disk'));
            if ($imagePath) {
                $response->update(['image_path' => $imagePath]);
            }
        }

        RecordActivity::run($response, 'community_response_submitted', "Response submitted by {$user->name} to question #{$question->id}.");

        return $response;
    }
}
