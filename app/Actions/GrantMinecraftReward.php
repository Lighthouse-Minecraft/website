<?php

namespace App\Actions;

use App\Models\MinecraftAccount;
use App\Models\MinecraftReward;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GrantMinecraftReward
{
    use AsAction;

    /**
     * Grant a reward to a Minecraft account, preventing duplicates by reward name per user.
     *
     * Sends the given RCON command, creates a MinecraftReward record, and logs the activity.
     * Returns false if the user already has a reward with the same name (idempotent).
     *
     * @param  MinecraftAccount  $account  The account receiving the reward.
     * @param  User  $user  The user who owns the account.
     * @param  string  $rewardName  Unique name for this reward type (e.g. "New Player Reward").
     * @param  string  $rewardDescription  Human-readable description (e.g. "96 Lumens").
     * @param  string  $rconCommand  The RCON command to execute (e.g. "money give PlayerName 96").
     */
    public function handle(
        MinecraftAccount $account,
        User $user,
        string $rewardName,
        string $rewardDescription,
        string $rconCommand,
    ): bool {
        $alreadyGranted = MinecraftReward::where('user_id', $user->id)
            ->where('reward_name', $rewardName)
            ->exists();

        if ($alreadyGranted) {
            return false;
        }

        SendMinecraftCommand::dispatch(
            $rconCommand,
            'reward',
            $account->username,
            $user,
            ['action' => 'grant_reward', 'reward_name' => $rewardName]
        );

        MinecraftReward::create([
            'user_id' => $user->id,
            'minecraft_account_id' => $account->id,
            'reward_name' => $rewardName,
            'reward_description' => $rewardDescription,
        ]);

        RecordActivity::handle(
            $user,
            'minecraft_reward_granted',
            "Granted {$rewardName}: {$rewardDescription} to {$account->username}"
        );

        return true;
    }
}
