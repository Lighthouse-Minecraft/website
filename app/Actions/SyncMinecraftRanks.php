<?php

namespace App\Actions;

use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncMinecraftRanks
{
    use AsAction;

    /**
     * Sync all of a user's active Minecraft accounts to their current membership rank.
     *
     * Sends `lh setmember <username> <rank>` for each active account.
     * Accounts for users below the server access threshold (Drifter, Stowaway)
     * are skipped — they should not be on the server in the first place.
     */
    public function handle(User $user): void
    {
        $rank = $user->membership_level->minecraftRank();

        if ($rank === null) {
            // User is below server access threshold — no MC rank command to send
            return;
        }

        $activeAccounts = $user->minecraftAccounts()->active()->get();

        foreach ($activeAccounts as $account) {
            SendMinecraftCommand::dispatch(
                "lh setmember {$account->username} {$rank}",
                'rank',
                $account->username,
                $user,
                ['action' => 'sync_rank', 'membership_level' => $user->membership_level->value]
            );

            RecordActivity::handle(
                $user,
                'minecraft_rank_synced',
                "Synced Minecraft rank to {$rank} for {$account->username}"
            );
        }
    }
}
