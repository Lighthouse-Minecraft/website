<?php

namespace App\Actions;

use App\Models\MinecraftAccount;
use App\Models\User;
use App\Services\MinecraftRconService;
use Lorisleiva\Actions\Concerns\AsAction;

class RevokeMinecraftAccount
{
    use AsAction;

    /**
     * Revoke a Minecraft account from a user (admin action)
     */
    public function handle(MinecraftAccount $account, User $admin): array
    {
        // Verify admin permission
        if (! $admin->isAdmin()) {
            return [
                'success' => false,
                'message' => 'You do not have permission to revoke accounts.',
            ];
        }

        $username = $account->username;
        $accountType = $account->account_type;
        $affectedUser = $account->user;

        // Send whitelist remove command synchronously for immediate effect
        $rconService = app(MinecraftRconService::class);
        $rconService->executeCommand(
            $account->whitelistRemoveCommand(),
            'whitelist',
            $account->command_id,
            $admin,
            ['action' => 'revoke', 'affected_user_id' => $affectedUser->id]
        );

        // Delete the account
        $account->delete();

        // Record activity for both admin and affected user
        RecordActivity::handle(
            $affectedUser,
            'minecraft_account_revoked',
            "Admin {$admin->name} revoked {$accountType->label()} account: {$username}"
        );

        return [
            'success' => true,
            'message' => 'Minecraft account revoked successfully.',
        ];
    }
}
