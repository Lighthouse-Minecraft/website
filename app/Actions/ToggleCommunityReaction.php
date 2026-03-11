<?php

namespace App\Actions;

use App\Models\CommunityReaction;
use App\Models\CommunityResponse;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class ToggleCommunityReaction
{
    use AsAction;

    public const ALLOWED_EMOJIS = ['❤️', '😂', '🙏', '👏', '🔥', '⛵'];

    public function handle(CommunityResponse $response, User $user, string $emoji): bool
    {
        if (! in_array($emoji, self::ALLOWED_EMOJIS)) {
            throw new \InvalidArgumentException("Emoji '{$emoji}' is not allowed.");
        }

        $existing = CommunityReaction::where('community_response_id', $response->id)
            ->where('user_id', $user->id)
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        CommunityReaction::create([
            'community_response_id' => $response->id,
            'user_id' => $user->id,
            'emoji' => $emoji,
        ]);

        return true;
    }
}
