<?php

namespace App\Actions;

use App\Enums\MinecraftAccountStatus;
use App\Models\MinecraftAccount;
use App\Models\User;
use App\Services\MinecraftRconService;
use Illuminate\Support\Facades\Log;
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

        $rconService = app(MinecraftRconService::class);

        // Reset the player's MC rank to default synchronously before removing from whitelist
        $rconService->executeCommand(
            "lh setmember {$account->username} default",
            'rank',
            $account->username,
            $admin,
            ['action' => 'revoke_rank_reset', 'affected_user_id' => $affectedUser->id]
        );

        // Remove from whitelist synchronously; only delete the record if it succeeds
        $whitelistResult = $rconService->executeCommand(
            $account->whitelistRemoveCommand(),
            'whitelist',
            $account->username,
            $admin,
            ['action' => 'revoke', 'affected_user_id' => $affectedUser->id]
        );

        if (! $whitelistResult['success']) {
            Log::error('Failed to remove whitelist during account revocation', [
                'account_id' => $account->id,
                'username' => $account->username,
                'response' => $whitelistResult['response'] ?? null,
                'error' => $whitelistResult['error'] ?? null,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to remove player from server whitelist. Account has not been removed.',
            ];
        }

        // Soft-disable the account (preserve record for audit trail)
        $account->status = MinecraftAccountStatus::Removed;
        $account->save();

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
