<?php

namespace App\Actions;

use App\Models\MinecraftAccount;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class GrantNewPlayerReward
{
    use AsAction;

    public const REWARD_NAME = 'New Player Reward';

    /**
     * Grant the new-player Lumen reward if enabled and not already granted.
     *
     * Checks the feature toggle, calculates the Lumen amount from config,
     * and delegates to GrantMinecraftReward for RCON dispatch, record creation,
     * and activity logging.
     */
    public function handle(MinecraftAccount $account, User $user): void
    {
        if (! config('lighthouse.minecraft.rewards.new_player_enabled')) {
            return;
        }

        $diamonds = (int) config('lighthouse.minecraft.rewards.new_player_diamonds');
        $exchangeRate = (int) config('lighthouse.minecraft.rewards.new_player_exchange_rate');
        $lumens = $diamonds * $exchangeRate;

        if ($lumens <= 0) {
            Log::warning('New-player reward skipped: calculated lumen amount is not positive', [
                'diamonds' => $diamonds,
                'exchange_rate' => $exchangeRate,
                'user_id' => $user->id,
            ]);

            return;
        }

        GrantMinecraftReward::run(
            $account,
            $user,
            self::REWARD_NAME,
            "{$lumens} Lumens",
            "money give {$account->username} {$lumens}",
        );
    }
}
