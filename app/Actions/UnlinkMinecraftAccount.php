<?php

namespace App\Actions;

use App\Models\MinecraftAccount;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class UnlinkMinecraftAccount
{
    use AsAction;

    /**
     * Unlink a Minecraft account from a user
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

        $username = $account->username;
        $accountType = $account->account_type;

        // Send whitelist remove command asynchronously
        SendMinecraftCommand::dispatch(
            "whitelist remove {$username}",
            'whitelist',
            $username,
            $user,
            ['action' => 'unlink']
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
