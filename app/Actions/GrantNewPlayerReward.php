<?php

namespace App\Actions;

use App\Models\MinecraftAccount;
use App\Models\User;
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

        $diamonds = config('lighthouse.minecraft.rewards.new_player_diamonds');
        $exchangeRate = config('lighthouse.minecraft.rewards.new_player_exchange_rate');
        $lumens = $diamonds * $exchangeRate;

        GrantMinecraftReward::run(
            $account,
            $user,
            self::REWARD_NAME,
            "{$lumens} Lumens",
            "money give {$account->username} {$lumens}",
        );
    }
}
