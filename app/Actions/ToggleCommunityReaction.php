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

        // Check if user already reacted with this exact emoji (toggle off)
        $existing = CommunityReaction::where('community_response_id', $response->id)
            ->where('user_id', $user->id)
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        // Remove any existing reaction by this user on this response (one emoji per user)
        CommunityReaction::where('community_response_id', $response->id)
            ->where('user_id', $user->id)
            ->delete();

        CommunityReaction::create([
            'community_response_id' => $response->id,
            'user_id' => $user->id,
            'emoji' => $emoji,
        ]);

        return true;
    }
}
