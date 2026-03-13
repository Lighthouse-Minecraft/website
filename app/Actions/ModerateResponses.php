<?php

namespace App\Actions;

use App\Enums\CommunityResponseStatus;
use App\Models\CommunityResponse;
use App\Models\User;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class ModerateResponses
{
    use AsAction;

    /** @param Collection<int, \App\Models\CommunityResponse> $responses */
    public function handle(Collection $responses, User $staff, CommunityResponseStatus $outcome): int
    {
        if (! in_array($outcome, [CommunityResponseStatus::Approved, CommunityResponseStatus::Rejected])) {
            throw new \InvalidArgumentException('Outcome must be Approved or Rejected.');
        }

        $count = 0;

        foreach ($responses as $response) {
            if (! $response->isEditable()) {
                continue;
            }

            $response->status = $outcome;
            $response->reviewed_by = $staff->id;
            $response->reviewed_at = now();

            if ($outcome === CommunityResponseStatus::Approved) {
                $response->approved_at = now();
            }

            $response->save();

            $action = $outcome === CommunityResponseStatus::Approved
                ? 'community_response_approved'
                : 'community_response_rejected';

            RecordActivity::run($response, $action, "Response #{$response->id} {$outcome->value} by {$staff->name}.");

            $count++;
        }

        return $count;
    }
}
