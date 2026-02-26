<?php

namespace App\Actions;

use App\Enums\MinecraftAccountStatus;
use App\Models\MinecraftAccount;
use App\Models\User;
use App\Services\MinecraftRconService;
use Lorisleiva\Actions\Concerns\AsAction;

class UnlinkMinecraftAccount
{
    use AsAction;

    /**
     * Unlink the given Minecraft account from the specified user, execute server-side rank
     * reset, staff removal (if applicable), and whitelist removal via RCON, then delete the
     * account record.
     *
     * If the user does not own the account or the account is not in an active state, the unlink
     * is not performed and an appropriate failure message is returned.
     *
     * @param  MinecraftAccount  $account  The Minecraft account to unlink.
     * @param  User  $user  The user requesting the unlink; ownership is verified against the account.
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
        $rconService = app(MinecraftRconService::class);

        // Reset the player's MC rank to default before removing from whitelist
        $rconService->executeCommand(
            "lh setmember {$account->username} default",
            'rank',
            $account->username,
            $user,
            ['action' => 'unlink_rank_reset']
        );

        RecordActivity::handle(
            $user,
            'minecraft_rank_reset_requested',
            "Reset rank to default for {$username}"
        );

        // Remove staff position if the user holds one
        if ($user->staff_department !== null) {
            $rconService->executeCommand(
                "lh removestaff {$account->username}",
                'rank',
                $account->username,
                $user,
                ['action' => 'unlink_staff_reset']
            );

            RecordActivity::handle(
                $user,
                'minecraft_staff_position_removed',
                "Removed Minecraft staff position for {$username}"
            );
        }

        // Remove from whitelist using the correct command for account type
        $rconService->executeCommand(
            $account->whitelistRemoveCommand(),
            'whitelist',
            $account->username,
            $user,
            ['action' => 'unlink']
        );

        RecordActivity::handle(
            $user,
            'minecraft_whitelist_removal_requested',
            "Removed {$username} from server whitelist"
        );

        // Soft-disable the account (preserve record for audit trail)
        $account->status = MinecraftAccountStatus::Removed;
        $account->save();

        RecordActivity::handle(
            $user,
            'minecraft_account_removed',
            "Removed {$accountType->label()} account: {$username}"
        );

        return [
            'success' => true,
            'message' => 'Minecraft account removed successfully.',
        ];
    }
}
