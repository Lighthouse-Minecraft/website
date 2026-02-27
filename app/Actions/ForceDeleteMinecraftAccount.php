<?php

namespace App\Actions;

use App\Enums\MinecraftAccountStatus;
use App\Models\MinecraftAccount;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class ForceDeleteMinecraftAccount
{
    use AsAction;

    /**
     * Permanently delete a Removed Minecraft account (admin only).
     *
     * This releases the UUID so it can be re-registered by any user.
     * Only accounts in Removed status can be force-deleted.
     */
    public function handle(MinecraftAccount $account, User $admin): array
    {
        if (! $admin->isAdmin()) {
            return [
                'success' => false,
                'message' => 'You do not have permission to permanently delete accounts.',
            ];
        }

        if ($account->status !== MinecraftAccountStatus::Removed) {
            return [
                'success' => false,
                'message' => 'Only removed accounts can be permanently deleted.',
            ];
        }

        $username = $account->username;
        $accountType = $account->account_type;
        $affectedUser = $account->user;

        if (! $account->delete()) {
            return [
                'success' => false,
                'message' => "Failed to delete Minecraft account {$username} (ID: {$account->id}).",
            ];
        }

        RecordActivity::run(
            $affectedUser,
            'minecraft_account_permanently_deleted',
            "Admin {$admin->name} permanently deleted {$accountType->label()} account: {$username}"
        );

        return [
            'success' => true,
            'message' => 'Minecraft account permanently deleted. The UUID is now released.',
        ];
    }
}
