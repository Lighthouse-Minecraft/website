<?php

namespace App\Actions;

use App\Enums\MinecraftAccountStatus;
use App\Models\MinecraftAccount;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class UnlinkMinecraftAccount
{
    use AsAction;

    /**
     * Unlink the given Minecraft account from the specified user, queue server-side rank reset and whitelist removal, record related activities, and delete the account.
     *
     * If the user does not own the account or the account is not in an active state, the unlink is not performed and an appropriate failure message is returned.
     *
     * @param MinecraftAccount $account The Minecraft account to unlink.
     * @param User $user The user requesting the unlink; ownership is verified against the account.
     * @return array An associative array with keys:
     *               - `success` (bool): `true` when the account was unlinked, `false` on permission or state failure.
     *               - `message` (string): A human-readable status message.
     */
    public function handle(MinecraftAccount $account, User $user): array
    {
        // Verify ownership
        if ($account->user_id !== $user->id) {
            return [
                'success' => false,
                'message' => 'You do not have permission to unlink this account.',
            ];
        }

        // Only allow unlinking active accounts; verifying accounts use Cancel Verification
        if ($account->status !== MinecraftAccountStatus::Active) {
            return [
                'success' => false,
                'message' => 'This account cannot be unlinked in its current state.',
            ];
        }

        $username = $account->username;
        $accountType = $account->account_type;

        // Reset the player's MC rank to default before removing from whitelist
        SendMinecraftCommand::dispatch(
            "lh setmember {$account->command_id} default",
            'rank',
            $account->command_id,
            $user,
            ['action' => 'unlink_rank_reset']
        );

        RecordActivity::handle(
            $user,
            'minecraft_rank_reset_requested',
            "Queued rank reset to default for {$username}"
        );

        // Remove from whitelist using the correct command for account type
        SendMinecraftCommand::dispatch(
            $account->whitelistRemoveCommand(),
            'whitelist',
            $account->command_id,
            $user,
            ['action' => 'unlink']
        );

        RecordActivity::handle(
            $user,
            'minecraft_whitelist_removal_requested',
            "Queued removal of {$username} from server whitelist"
        );

        // Delete the account
        $account->delete();

        // Record activity
        RecordActivity::handle(
            $user,
            'minecraft_account_unlinked',
            "Unlinked {$accountType->label()} account: {$username}"
        );

        return [
            'success' => true,
            'message' => 'Minecraft account unlinked successfully.',
        ];
    }
}